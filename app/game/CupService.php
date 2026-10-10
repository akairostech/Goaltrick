<?php
declare(strict_types=1);

/**
 * Copa de liga: en cada país, todos los equipos de todas las divisiones a eliminación directa.
 * Se juega en la fecha de los amistosos (una ronda por semana). Si el número de equipos no es
 * potencia de 2, hay una ronda previa entre los equipos de las divisiones más bajas y los de
 * arriba pasan directamente. El equipo de la división inferior juega en casa; si hay empate,
 * penaltis. Mientras un equipo siga vivo en la copa no puede jugar amistosos.
 */
final class CupService
{
    public const STAGES = [
        'R1' => 'Ronda previa', 'R128' => 'Ronda de 128', 'R64' => 'Treintaidosavos de final', 'R32' => 'Dieciseisavos de final',
        'R16' => 'Octavos de final', 'QF' => 'Cuartos de final', 'SF' => 'Semifinales', 'F' => 'Final',
    ];

    public static function enabled(): bool
    {
        return Config::bool('cup', 'enabled', true);
    }

    public static function stageName(?string $code): string
    {
        return isset(self::STAGES[$code ?? '']) ? t(self::STAGES[$code]) : (string)$code;
    }

    /** Nombre de la ronda para guardar en la base de datos */
    private static function stageTx(?string $code): string
    {
        return isset(self::STAGES[$code ?? '']) ? tx(self::STAGES[$code]) : (string)$code;
    }

    private static function stageFor(int $teams): string
    {
        return match ($teams) { 2 => 'F', 4 => 'SF', 8 => 'QF', 16 => 'R16', 32 => 'R32', 64 => 'R64', 128 => 'R128', default => 'R1' };
    }

    public static function cupName(array $cup): string
    {
        $country = (string)DB::value('SELECT name FROM countries WHERE id = ?', [$cup['country_id']]);
        return t('Copa de {country}', ['country' => t($country)]);
    }

    /** Nombre de la copa para guardar en la base de datos (se traduce al mostrarlo) */
    public static function cupNameTx(array $cup): string
    {
        $country = (string)DB::value('SELECT name FROM countries WHERE id = ?', [$cup['country_id']]);
        return tx('Copa de {country}', ['country' => tx($country)]);
    }

    /** ¿Sigue el equipo en una copa en juego? */
    public static function isAlive(int $teamId): bool
    {
        return (bool)DB::value(
            "SELECT COUNT(*) FROM cup_entries e JOIN league_cups c ON c.id = e.cup_id
             WHERE e.team_id = ? AND e.eliminated_round IS NULL AND c.status = 'playing'",
            [$teamId]
        );
    }

    public static function teamCup(int $teamId, int $season): ?array
    {
        return DB::row('SELECT c.*, e.eliminated_round, e.seed FROM cup_entries e JOIN league_cups c ON c.id = e.cup_id WHERE e.team_id = ? AND c.season = ?', [$teamId, $season]);
    }

    private static function friendlySlots(int $season, ?string $after = null): array
    {
        return DB::all(
            "SELECT * FROM scheduled_events WHERE type = 'friendly_round' AND season = ? AND status = 'pending' AND run_at > ? ORDER BY run_at",
            [$season, $after ?? '1970-01-01 00:00:00']
        );
    }

    /** Crea la copa de cada país para la temporada y sortea la primera ronda */
    public static function createForSeason(int $season): void
    {
        if (!self::enabled()) {
            return;
        }
        $slots = self::friendlySlots($season, now());
        if (!$slots) {
            $slots = self::friendlySlots($season);
        }
        foreach (DB::all('SELECT DISTINCT country_id FROM league_units ORDER BY country_id') as $row) {
            $countryId = (int)$row['country_id'];
            if (DB::value('SELECT COUNT(*) FROM league_cups WHERE season = ? AND country_id = ?', [$season, $countryId])) {
                continue;
            }
            $teams = DB::all(
                'SELECT t.id, lu.level FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id
                 WHERE lu.country_id = ? AND t.is_national = 0 ORDER BY lu.level, RAND()',
                [$countryId]
            );
            $n = count($teams);
            if ($n < 2 || count($slots) < (int)ceil(log($n, 2))) {
                continue; // no quedan suficientes fechas esta temporada
            }
            DB::transaction(function () use ($season, $countryId, $teams, $slots) {
                $cupId = DB::insert('league_cups', ['season' => $season, 'country_id' => $countryId, 'created_at' => now()]);
                foreach ($teams as $i => $t) {
                    DB::insert('cup_entries', ['cup_id' => $cupId, 'team_id' => $t['id'], 'seed' => $i + 1]);
                }
                $cup = DB::row('SELECT * FROM league_cups WHERE id = ?', [$cupId]);
                self::drawRound($cup, $slots[0]);
            });
        }
    }

    /** Sortea la ronda actual de una copa para la fecha indicada */
    private static function drawRound(array $cup, array $slot): void
    {
        $alive = DB::all(
            'SELECT e.team_id, e.seed, lu.level FROM cup_entries e JOIN teams t ON t.id = e.team_id LEFT JOIN league_units lu ON lu.id = t.league_unit_id
             WHERE e.cup_id = ? AND e.eliminated_round IS NULL ORDER BY e.seed',
            [$cup['id']]
        );
        $n = count($alive);
        if ($n < 2) {
            return;
        }
        $p = 1 << (int)floor(log($n, 2));
        if ($n === $p) {
            $playing = $alive;
            $stage = self::stageFor($n);
        } else {
            // Ronda previa: juegan los de peor cabeza de serie, el resto pasa directamente
            $playing = array_slice($alive, $n - 2 * ($n - $p));
            $stage = 'R1';
        }
        shuffle($playing);
        $ids = array_map(fn($t) => (int)$t['team_id'], $playing);
        // Si alguno tenía un amistoso para ese día, se anula: la copa tiene prioridad
        if ($ids) {
            $in = implode(',', $ids);
            DB::exec("DELETE FROM matches WHERE type = 'friendly' AND status = 'scheduled' AND scheduled_at = ? AND (home_team_id IN ($in) OR away_team_id IN ($in))", [$slot['run_at']]);
        }
        for ($i = 0; $i + 1 < count($playing); $i += 2) {
            [$a, $b] = [$playing[$i], $playing[$i + 1]];
            // Juega en casa el equipo de la división más baja
            if ((int)$b['level'] > (int)$a['level'] || ((int)$b['level'] === (int)$a['level'] && mt_rand(0, 1))) {
                [$a, $b] = [$b, $a];
            }
            DB::insert('matches', [
                'season' => $cup['season'], 'week' => $slot['week'], 'round' => $cup['current_round'], 'type' => 'cup',
                'cup_id' => $cup['id'], 'cup_stage' => $stage, 'home_team_id' => $a['team_id'], 'away_team_id' => $b['team_id'],
                'scheduled_at' => $slot['run_at'],
            ]);
        }
    }

    /** Juega los partidos de copa de la fecha y prepara la siguiente ronda. Devuelve un resumen. */
    public static function playDue(string $until): string
    {
        $matches = DB::all("SELECT id, cup_id FROM matches WHERE type = 'cup' AND status = 'scheduled' AND scheduled_at <= ?", [$until]);
        if (!$matches) {
            return '';
        }
        foreach ($matches as $m) {
            MatchEngine::play((int)$m['id']);
        }
        $cupIds = array_unique(array_map(fn($m) => (int)$m['cup_id'], $matches));
        $finished = 0;
        foreach ($cupIds as $cupId) {
            DB::transaction(function () use ($cupId, $until, &$finished) {
                $cup = DB::row('SELECT * FROM league_cups WHERE id = ?', [$cupId]);
                if (!$cup || $cup['status'] !== 'playing') {
                    return;
                }
                $round = (int)$cup['current_round'];
                $played = DB::all("SELECT * FROM matches WHERE type = 'cup' AND cup_id = ? AND round = ? AND status = 'played'", [$cupId, $round]);
                $prize = Config::int('cup', 'round_prize', 8000);
                $name = self::cupNameTx($cup);
                foreach ($played as $m) {
                    $w = WorldCupService::winner($m);
                    $l = WorldCupService::loser($m);
                    DB::exec('UPDATE cup_entries SET eliminated_round = ? WHERE cup_id = ? AND team_id = ?', [$round, $cupId, $l]);
                    if ($prize > 0) {
                        Economy::transaction($w, $prize, 'prizes', tx('{cup}: prima por pasar de ronda', ['cup' => $name]), (int)$cup['season'], (int)$m['week']);
                    }
                    self::notify($l, tx('🏆 Tu equipo ha quedado eliminado de la {cup} ({stage}).', ['cup' => $name, 'stage' => self::stageTx($m['cup_stage'])]));
                    if ($m['cup_stage'] !== 'F') {
                        self::notify($w, tx('🏆 ¡Pasas de ronda en la {cup}! Ganaste en la ronda: {stage}.', ['cup' => $name, 'stage' => self::stageTx($m['cup_stage'])]));
                    }
                }
                $alive = (int)DB::value('SELECT COUNT(*) FROM cup_entries WHERE cup_id = ? AND eliminated_round IS NULL', [$cupId]);
                if ($alive <= 1) {
                    $final = null;
                    foreach ($played as $m) {
                        if ($m['cup_stage'] === 'F') {
                            $final = $m;
                        }
                    }
                    $winner = (int)DB::value('SELECT team_id FROM cup_entries WHERE cup_id = ? AND eliminated_round IS NULL', [$cupId]);
                    $runner = $final ? WorldCupService::loser($final) : null;
                    DB::update('league_cups', ['status' => 'finished', 'winner_team_id' => $winner ?: null, 'runnerup_team_id' => $runner], 'id = ?', [$cupId]);
                    if ($winner) {
                        Economy::transaction($winner, Config::int('cup', 'winner_prize', 150000), 'prizes', tx('Campeón de la {cup}', ['cup' => $name]), (int)$cup['season']);
                        self::notify($winner, tx('🏆 ¡CAMPEONES de la {cup}! El trofeo ya está en tu vitrina.', ['cup' => $name]));
                    }
                    if ($runner) {
                        Economy::transaction($runner, Config::int('cup', 'runnerup_prize', 60000), 'prizes', tx('Subcampeón de la {cup}', ['cup' => $name]), (int)$cup['season']);
                    }
                    $finished++;
                    return;
                }
                $next = self::friendlySlots((int)$cup['season'], $until);
                if (!$next) {
                    DB::exec("UPDATE league_cups SET status = 'finished' WHERE id = ?", [$cupId]);
                    return;
                }
                DB::exec('UPDATE league_cups SET current_round = current_round + 1 WHERE id = ?', [$cupId]);
                $cup['current_round'] = $round + 1;
                self::drawRound($cup, $next[0]);
            });
        }
        $log = tx('Copa: {n} partidos', ['n' => count($matches)]);
        return $finished ? tx_join($log, tx('· {n} copa(s) terminada(s)', ['n' => $finished])) : $log;
    }

    private static function notify(int $teamId, string $msg): void
    {
        $t = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$teamId]);
        if ($t && !(int)$t['is_bot']) {
            DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'cup', 'message' => $msg]);
        }
    }

    /** Fin de temporada: se cierran las copas que no hubieran terminado */
    public static function seasonEnd(int $season): void
    {
        DB::exec("UPDATE league_cups SET status = 'finished' WHERE season = ? AND status = 'playing'", [$season]);
    }
}
