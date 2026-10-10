<?php
declare(strict_types=1);

/**
 * Inteligencia de los equipos controlados por la máquina: mantienen la plantilla
 * y participan en el mercado de fichajes para que siempre haya actividad.
 */
final class BotAI
{
    /** Garantiza que ningún bot se quede con menos de 16 jugadores */
    public static function fillSquads(): void
    {
        $rows = DB::all(
            'SELECT t.id, t.country_id, lu.level, (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.retired = 0) AS n
             FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.is_bot = 1'
        );
        $roles = ['GK', 'CD', 'WB', 'IM', 'W', 'FW'];
        foreach ($rows as $r) {
            $need = 18 - (int)$r['n'];
            for ($i = 0; $i < $need; $i++) {
                $role = self::neededRole((int)$r['id']) ?? $roles[array_rand($roles)];
                PlayerFactory::create([
                    'role' => $role, 'team_id' => (int)$r['id'], 'home_country' => (int)$r['country_id'],
                    'level' => TeamService::levelStrength((int)$r['level']) - 0.8, 'age_min' => 17, 'age_max' => 21,
                ]);
            }
        }
    }

    private static function neededRole(int $teamId): ?string
    {
        $counts = array_column(DB::all('SELECT role, COUNT(*) n FROM players WHERE team_id = ? AND retired = 0 GROUP BY role', [$teamId]), 'n', 'role');
        $wanted = ['GK' => 2, 'CD' => 3, 'WB' => 3, 'IM' => 4, 'W' => 3, 'FW' => 3];
        foreach ($wanted as $role => $n) {
            if ((int)($counts[$role] ?? 0) < $n) {
                return $role;
            }
        }
        return null;
    }

    /** Acciones semanales: poner jugadores a la venta */
    public static function weekly(): void
    {
        if (!Config::bool('game', 'bots_use_market', true)) {
            self::fillSquads();
            return;
        }
        $bots = DB::all('SELECT t.*, (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.retired = 0) AS n FROM teams t WHERE t.is_bot = 1 AND t.is_national = 0');
        foreach ($bots as $b) {
            $n = (int)$b['n'];
            $sell = 0;
            if ($n > 22) {
                $sell = $n - 21;
            } elseif ($n >= 17 && mt_rand(1, 100) <= 12) {
                $sell = 1;
            }
            if (!$sell) {
                continue;
            }
            $players = DB::all(
                "SELECT p.* FROM players p WHERE p.team_id = ? AND p.retired = 0 AND p.injury_weeks <= 0
                 AND NOT EXISTS (SELECT 1 FROM transfer_listings l WHERE l.player_id = p.id AND l.status = 'active')
                 ORDER BY RAND() LIMIT " . (int)$sell,
                [$b['id']]
            );
            foreach ($players as $p) {
                $price = (int)(round(PlayerFactory::value($p) * rnd(0.45, 0.85) / 1000) * 1000);
                TransferService::list($b, (int)$p['id'], max(1000, $price));
            }
        }
        self::fillSquads();
    }

    /** Pujas automáticas de los bots (se llama desde el planificador) */
    public static function bids(?string $until = null, int $round = 1): void
    {
        if (!Config::bool('game', 'bots_use_market', true)) {
            return;
        }
        // $until: (modo pruebas) pujar en todas las subastas que cierran antes de esa fecha
        if ($until === null) {
            $last = (int)(GameState::get('last_bot_bids') ?? 0);
            if (time() - $last < 300) {
                return;
            }
            GameState::set('last_bot_bids', time());
        }

        $listings = DB::all(
            "SELECT l.*, p.* , l.id AS listing_id FROM transfer_listings l JOIN players p ON p.id = l.player_id
             WHERE l.status = 'active' AND l.deadline > ? AND l.deadline <= ?",
            [now(), $until ?? date('Y-m-d H:i:s', time() + 12 * 3600)]
        );
        foreach ($listings as $l) {
            if ($until === null && mt_rand(1, 100) > 60) {
                continue;
            }
            $value = PlayerFactory::value($l);
            $next = TransferService::minNextBid($l);
            $limit = $value * rnd(0.55, 0.95);
            if ($next > $limit) {
                continue;
            }
            $bot = DB::row(
                "SELECT t.* FROM teams t WHERE t.is_bot = 1 AND t.is_national = 0 AND t.id <> ? AND t.cash > ?
                   AND (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.retired = 0) < 24
                   AND (t.id <> ? OR ? IS NULL)
                 ORDER BY RAND() LIMIT 1",
                [$l['seller_team_id'], $next + 150000, $l['highest_bidder_id'] ?? 0, $l['highest_bidder_id']]
            );
            if ($bot) {
                TransferService::bid($bot, (int)$l['listing_id'], $next);
            }
        }
        // En modo pruebas simulamos varias rondas de pujas seguidas
        if ($until !== null && $round < 6) {
            self::bids($until, $round + 1);
        }
    }
}
