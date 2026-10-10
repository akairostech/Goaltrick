<?php
declare(strict_types=1);

/** Partidos amistosos */
final class FriendlyService
{
    public static function nextSlot(): ?array
    {
        return Scheduler::nextEvent('friendly_round');
    }

    public static function hasFriendly(int $teamId, string $at): bool
    {
        return (bool)DB::value(
            "SELECT COUNT(*) FROM matches WHERE type = 'friendly' AND status = 'scheduled' AND scheduled_at = ? AND (home_team_id = ? OR away_team_id = ?)",
            [$at, $teamId, $teamId]
        );
    }

    public static function challenge(array $from, int $toId, bool $home): ?string
    {
        $slot = self::nextSlot();
        if (!$slot) {
            return t('No hay fecha disponible para amistosos.');
        }
        if (CupService::isAlive((int)$from['id'])) {
            return t('Tu equipo sigue vivo en la Copa: no puede jugar amistosos hasta que quede eliminado.');
        }
        if (CupService::isAlive($toId)) {
            return t('Ese equipo sigue jugando la Copa y no puede jugar amistosos.');
        }
        if ($toId === (int)$from['id']) {
            return t('No puedes jugar contra ti mismo.');
        }
        $to = DB::row('SELECT * FROM teams WHERE id = ?', [$toId]);
        if (!$to) {
            return t('Equipo no encontrado.');
        }
        if (self::hasFriendly((int)$from['id'], $slot['run_at'])) {
            return t('Ya tienes un amistoso programado para esta semana.');
        }
        if (self::hasFriendly($toId, $slot['run_at'])) {
            return t('Ese equipo ya tiene un amistoso esta semana.');
        }
        if (DB::value("SELECT COUNT(*) FROM friendly_requests WHERE from_team_id = ? AND status = 'pending'", [$from['id']])) {
            return t('Ya tienes una invitación pendiente. Espera la respuesta o cancélala.');
        }
        $reqId = DB::insert('friendly_requests', ['from_team_id' => $from['id'], 'to_team_id' => $toId, 'created_at' => now()]);
        if ((int)$to['is_bot']) {
            // Los bots aceptan siempre
            self::createMatch($reqId, $home ? (int)$from['id'] : $toId, $home ? $toId : (int)$from['id'], $slot);
        } else {
            // Entre usuarios, el que invita juega siempre como local
            DB::insert('team_events', ['team_id' => $toId, 'created_at' => now(), 'type' => 'friendly',
                'message' => tx('🤝 {team} te invita a jugar un amistoso. Respóndele en «Amistosos».', ['team' => $from['name']])]);
        }
        return null;
    }

    private static function createMatch(int $reqId, int $homeId, int $awayId, array $slot): void
    {
        $mid = DB::insert('matches', [
            'season' => $slot['season'], 'week' => $slot['week'], 'type' => 'friendly',
            'home_team_id' => $homeId, 'away_team_id' => $awayId, 'scheduled_at' => $slot['run_at'],
        ]);
        DB::exec("UPDATE friendly_requests SET status = 'accepted', match_id = ? WHERE id = ?", [$mid, $reqId]);
    }

    public static function respond(array $team, int $reqId, bool $accept): ?string
    {
        $r = DB::row("SELECT * FROM friendly_requests WHERE id = ? AND to_team_id = ? AND status = 'pending'", [$reqId, $team['id']]);
        if (!$r) {
            return t('Invitación no encontrada.');
        }
        if (!$accept) {
            DB::exec("UPDATE friendly_requests SET status = 'rejected' WHERE id = ?", [$reqId]);
            DB::insert('team_events', ['team_id' => $r['from_team_id'], 'created_at' => now(), 'type' => 'friendly',
                'message' => tx('{team} ha rechazado tu invitación a un amistoso.', ['team' => $team['name']])]);
            return null;
        }
        $slot = self::nextSlot();
        if (!$slot) {
            return t('No hay fecha disponible.');
        }
        if (CupService::isAlive((int)$team['id']) || CupService::isAlive((int)$r['from_team_id'])) {
            return t('Uno de los dos equipos sigue jugando la Copa: no se pueden jugar amistosos hasta quedar eliminado.');
        }
        if (self::hasFriendly((int)$team['id'], $slot['run_at']) || self::hasFriendly((int)$r['from_team_id'], $slot['run_at'])) {
            return t('Alguno de los dos equipos ya tiene un amistoso esta semana.');
        }
        // El que invita juega en casa
        self::createMatch($reqId, (int)$r['from_team_id'], (int)$team['id'], $slot);
        DB::insert('team_events', ['team_id' => $r['from_team_id'], 'created_at' => now(), 'type' => 'friendly',
            'message' => tx('🤝 {team} ha aceptado jugar un amistoso.', ['team' => $team['name']])]);
        return null;
    }

    public static function cancel(array $team, int $reqId): void
    {
        DB::exec("UPDATE friendly_requests SET status = 'rejected' WHERE id = ? AND from_team_id = ? AND status = 'pending'", [$reqId, $team['id']]);
    }
}
