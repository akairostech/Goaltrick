<?php
declare(strict_types=1);

/**
 * Planificador: ejecuta los eventos pendientes (jornadas, entrenamiento, economía,
 * fin de temporada), cierra subastas y finaliza obras.
 */
final class Scheduler
{
    private static function lock(): bool
    {
        return (int)DB::value("SELECT GET_LOCK('goaltrick_scheduler', 0)") === 1;
    }

    private static function unlock(): void
    {
        DB::value("SELECT RELEASE_LOCK('goaltrick_scheduler')");
    }

    /** Ejecutado en cada visita (si auto_update está activo), limitado a una vez cada 30 s */
    public static function tickFromWeb(): void
    {
        $last = (int)(GameState::get('last_tick') ?? 0);
        if (time() - $last < 30) {
            return;
        }
        GameState::set('last_tick', time());
        try {
            self::runDue(20);
        } catch (Throwable $e) {
            error_log('Scheduler: ' . $e->getMessage());
            if (Config::bool('app', 'debug')) {
                throw $e;
            }
        }
    }

    /** Ejecuta todo lo que esté pendiente. Devuelve un log de lo realizado. */
    public static function runDue(int $maxEvents = 100): array
    {
        if (!self::lock()) {
            return [t('El planificador ya se está ejecutando en otro proceso.')];
        }
        $log = [];
        try {
            @set_time_limit(0);
            $n = TransferService::processDue();
            if ($n) {
                $log[] = t('Traspasos completados: {n}', ['n' => $n]);
            }
            Economy::finishConstructions();
            for ($i = 0; $i < $maxEvents; $i++) {
                $ev = DB::row("SELECT * FROM scheduled_events WHERE status = 'pending' AND run_at <= ? ORDER BY run_at, id LIMIT 1", [now()]);
                if (!$ev) {
                    break;
                }
                $log[] = self::execute($ev);
            }
            BotAI::bids();
        } finally {
            self::unlock();
        }
        return $log;
    }

    /** (Administración) Ejecuta el siguiente evento sin esperar a su hora */
    public static function forceNext(): string
    {
        if (!self::lock()) {
            return t('El planificador ya se está ejecutando.');
        }
        try {
            @set_time_limit(0);
            $ev = DB::row("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at, id LIMIT 1");
            if (!$ev) {
                return t('No hay eventos pendientes.');
            }
            // Los bots pujan y se cierran las subastas que acabarían antes de este evento
            BotAI::bids($ev['run_at']);
            DB::exec("UPDATE transfer_listings SET deadline = ? WHERE status = 'active' AND deadline <= ?", [now(), $ev['run_at']]);
            TransferService::processDue();
            return self::execute($ev);
        } finally {
            self::unlock();
        }
    }

    private static function execute(array $ev): string
    {
        // Marcar primero para no repetir si algo falla a mitad
        DB::exec("UPDATE scheduled_events SET status = 'done', executed_at = ? WHERE id = ? AND status = 'pending'", [now(), $ev['id']]);
        $season = (int)$ev['season'];
        $week = (int)$ev['week'];
        if ($season === GameState::season()) {
            GameState::set('week', $week);
        }
        $label = '';
        switch ($ev['type']) {
            case 'league_round':
                $ids = DB::all("SELECT id FROM matches WHERE type = 'league' AND season = ? AND round = ? AND status = 'scheduled'", [$season, $ev['round']]);
                foreach ($ids as $m) {
                    MatchEngine::play((int)$m['id']);
                }
                $label = tx('Jornada {round} de liga jugada ({n} partidos)', ['round' => $ev['round'], 'n' => count($ids)]);
                break;
            case 'friendly_round':
                // Rechazar invitaciones no respondidas (los partidos de copa se juegan a la vez que los amistosos)
                DB::exec("UPDATE friendly_requests SET status = 'expired' WHERE status = 'pending'");
                $ids = DB::all("SELECT id FROM matches WHERE type = 'friendly' AND status = 'scheduled' AND scheduled_at <= ?", [$ev['run_at']]);
                foreach ($ids as $m) {
                    MatchEngine::play((int)$m['id']);
                }
                $label = tx('Amistosos jugados ({n})', ['n' => count($ids)]);
                $cupLog = CupService::playDue($ev['run_at']);
                if ($cupLog !== '') {
                    $label = tx('{a} · {b}', ['a' => $cupLog, 'b' => $label]);
                }
                break;
            case 'worldcup_round':
                $label = WorldCupService::playRound($season, (int)$ev['round']);
                break;
            case 'training':
                Training::weeklyUpdate();
                $label = tx('Entrenamiento semanal (semana {week})', ['week' => $week]);
                break;
            case 'economy':
                Economy::weeklyUpdate($season, $week);
                BotAI::weekly();
                $label = tx('Actualización económica (semana {week})', ['week' => $week]);
                break;
            case 'season_end':
                LeagueService::seasonEnd($season);
                $label = tx('Fin de la temporada {season}. Comienza la temporada {next}', ['season' => $season, 'next' => $season + 1]);
                break;
            default:
                $label = tx('Evento desconocido: {type}', ['type' => $ev['type']]);
        }
        GameState::set('last_event', tx('{event} — {time}', ['event' => $label, 'time' => now()]));
        return tdb($label);
    }

    public static function nextEvent(?string $type = null): ?array
    {
        if ($type) {
            return DB::row("SELECT * FROM scheduled_events WHERE status = 'pending' AND type = ? ORDER BY run_at LIMIT 1", [$type]);
        }
        return DB::row("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at LIMIT 1");
    }

    public static function eventName(string $type): string
    {
        $names = [
            'league_round' => 'Jornada de liga', 'friendly_round' => 'Amistosos', 'training' => 'Entrenamiento',
            'economy' => 'Economía', 'season_end' => 'Fin de temporada', 'worldcup_round' => 'Mundial',
        ];
        return isset($names[$type]) ? t($names[$type]) : $type;
    }
}
