<?php
declare(strict_types=1);

/**
 * Patrocinio principal.
 *
 * Antes de cada temporada cada club recibe 3 ofertas de empresas (según la división en la que juega):
 *  - Contrato fijo: ingreso semanal alto y una prima de firma. Dura 1 temporada.
 *  - Por objetivos: ingreso semanal menor, pero prima por cada victoria en liga y un gran premio si es campeón.
 *  - Largo plazo: dura 2 o 3 temporadas con el mismo importe (aunque el equipo baje) y una buena prima de firma.
 * El mánager elige una antes de la fecha límite (la primera actualización económica de la temporada);
 * si no lo hace se firma automáticamente la oferta de contrato fijo. Los equipos de la máquina eligen solos.
 */
final class SponsorService
{
    public const TYPES = [
        'stable'      => ['Contrato fijo', 'Ingreso semanal alto y seguro, más una prima por firmar. Dura una temporada.'],
        'performance' => ['Por objetivos', 'Menos dinero fijo, pero una prima por cada victoria en liga y un gran premio si sois campeones. Dura una temporada.'],
        'long'        => ['Largo plazo', 'El mismo ingreso durante varias temporadas, aunque el equipo descienda, y una buena prima de firma.'],
    ];

    public static function logoUrl(int $companyId): string
    {
        return asset(sprintf('img/sponsors/%03d.svg', $companyId));
    }

    /** Ingreso semanal base según la división */
    public static function base(int $level): int
    {
        $level = max(1, $level);
        $v = Config::int('sponsors', 'base_level_' . min(5, $level), 0);
        if ($v <= 0) {
            $v = [1 => 40000, 2 => 25000, 3 => 15000, 4 => 10000, 5 => 7000][min(5, $level)];
        }
        // Divisiones por debajo de la 5ª: un 25% menos por cada nivel extra
        return (int)round($v * pow(0.75, max(0, $level - 5)));
    }

    public static function activeContract(int $teamId, ?int $season = null): ?array
    {
        $season = $season ?? GameState::season();
        return DB::row(
            "SELECT sc.*, c.name AS company, c.sector, c.slogan, c.prestige FROM sponsor_contracts sc JOIN companies c ON c.id = sc.company_id
             WHERE sc.team_id = ? AND sc.status = 'active' AND sc.season_from <= ? AND sc.season_to >= ? ORDER BY sc.id DESC LIMIT 1",
            [$teamId, $season, $season]
        );
    }

    public static function offers(int $teamId): array
    {
        return DB::all(
            "SELECT sc.*, c.name AS company, c.sector, c.slogan, c.prestige FROM sponsor_contracts sc JOIN companies c ON c.id = sc.company_id
             WHERE sc.team_id = ? AND sc.status = 'offer' ORDER BY FIELD(sc.type, 'stable', 'performance', 'long')",
            [$teamId]
        );
    }

    public static function weeklyFor(int $teamId): int
    {
        $c = self::activeContract($teamId);
        return $c ? (int)$c['weekly_amount'] : 0;
    }

    /** Fecha límite para firmar las ofertas de la temporada */
    private static function deadline(int $season): string
    {
        $ev = DB::value("SELECT run_at FROM scheduled_events WHERE type = 'economy' AND season = ? AND status = 'pending' ORDER BY run_at LIMIT 1", [$season]);
        $min = time() + 86400; // al menos un día para decidir
        $days = time() + Config::int('sponsors', 'offer_days', 3) * 86400;
        if ($ev && strtotime((string)$ev) > $min) {
            return (string)$ev;
        }
        return date('Y-m-d H:i:s', $days);
    }

    /** Genera las 3 ofertas para un equipo (o firma directamente si es de la máquina) */
    public static function generateOffers(int $teamId, ?int $season = null): void
    {
        $season = $season ?? GameState::season();
        $team = DB::row('SELECT t.*, lu.level FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.id = ?', [$teamId]);
        if (!$team) {
            return;
        }
        DB::exec("UPDATE sponsor_contracts SET status = 'rejected' WHERE team_id = ? AND status = 'offer'", [$teamId]);
        $level = (int)$team['level'];
        $base = self::base($level);
        // Empresas más importantes para las divisiones altas
        [$pMin, $pMax] = match (true) {
            $level <= 1 => [4, 5],
            $level === 2 => [3, 4],
            $level === 3 => [2, 3],
            default => [1, 2],
        };
        $companies = DB::all('SELECT * FROM companies WHERE prestige BETWEEN ? AND ? ORDER BY RAND() LIMIT 3', [$pMin, $pMax]);
        if (count($companies) < 3) {
            $companies = DB::all('SELECT * FROM companies ORDER BY RAND() LIMIT 3');
        }
        if (count($companies) < 3) {
            return; // no hay empresas cargadas
        }
        $types = ['stable', 'performance', 'long'];
        $expires = self::deadline($season);
        $ids = [];
        foreach ($types as $i => $type) {
            $co = $companies[$i];
            $f = (0.9 + (int)$co['prestige'] * 0.04) * rnd(0.93, 1.07);
            $round = fn(float $v) => (int)(round($v / 100) * 100);
            $o = match ($type) {
                'stable' => ['weekly_amount' => $round($base * $f), 'signing_bonus' => $round($base * 1.0 * $f), 'win_bonus' => 0, 'title_bonus' => 0, 'season_to' => $season],
                'performance' => ['weekly_amount' => $round($base * 0.65 * $f), 'signing_bonus' => 0, 'win_bonus' => $round($base * 0.3 * $f), 'title_bonus' => $round($base * 8 * $f), 'season_to' => $season],
                default => ['weekly_amount' => $round($base * 0.9 * $f), 'signing_bonus' => $round($base * 3 * $f), 'win_bonus' => 0, 'title_bonus' => 0, 'season_to' => $season + mt_rand(1, 2)],
            };
            $ids[$type] = DB::insert('sponsor_contracts', $o + [
                'team_id' => $teamId, 'company_id' => $co['id'], 'type' => $type, 'season_from' => $season,
                'status' => 'offer', 'expires_at' => $expires, 'created_at' => now(),
            ]);
        }
        if ((int)$team['is_bot']) {
            $r = mt_rand(1, 100);
            self::sign($teamId, $ids[$r <= 50 ? 'stable' : ($r <= 80 ? 'long' : 'performance')], false);
        } else {
            DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'sponsor',
                'message' => '💼 Tienes 3 ofertas de patrocinio para la temporada ' . $season . '. Elige una antes del ' . fdate($expires) . ' en «Patrocinio».']);
        }
    }

    /** Firma una oferta. Devuelve null o un mensaje de error. */
    public static function sign(int $teamId, int $offerId, bool $notify = true): ?string
    {
        return DB::transaction(function () use ($teamId, $offerId, $notify) {
            $o = DB::row("SELECT sc.*, c.name AS company FROM sponsor_contracts sc JOIN companies c ON c.id = sc.company_id
                          WHERE sc.id = ? AND sc.team_id = ? AND sc.status = 'offer' FOR UPDATE", [$offerId, $teamId]);
            if (!$o) {
                return 'La oferta ya no está disponible.';
            }
            if (self::activeContract($teamId, (int)$o['season_from'])) {
                return 'Ya tienes un patrocinador para esta temporada.';
            }
            DB::exec("UPDATE sponsor_contracts SET status = 'rejected' WHERE team_id = ? AND status = 'offer' AND id <> ?", [$teamId, $offerId]);
            DB::exec("UPDATE sponsor_contracts SET status = 'active', signed_at = ?, expires_at = NULL, total_paid = signing_bonus WHERE id = ?", [now(), $offerId]);
            if ((int)$o['signing_bonus'] > 0) {
                Economy::transaction($teamId, (int)$o['signing_bonus'], 'sponsors', 'Prima de firma: ' . $o['company']);
            }
            if ($notify) {
                DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'sponsor',
                    'message' => '🤝 Contrato de patrocinio firmado con ' . $o['company'] . ' (' . self::TYPES[$o['type']][0] . ').']);
            }
            return null;
        });
    }

    /** Firma automáticamente (contrato fijo) las ofertas cuyo plazo ha vencido */
    public static function autoSignExpired(): void
    {
        $teams = DB::all("SELECT DISTINCT team_id FROM sponsor_contracts WHERE status = 'offer' AND expires_at <= ?", [now()]);
        foreach ($teams as $t) {
            $tid = (int)$t['team_id'];
            $offers = self::offers($tid);
            if (!$offers) {
                continue;
            }
            self::sign($tid, (int)$offers[0]['id'], false);
            DB::insert('team_events', ['team_id' => $tid, 'created_at' => now(), 'type' => 'sponsor',
                'message' => '💼 No elegiste patrocinador a tiempo: se ha firmado automáticamente el contrato fijo con ' . $offers[0]['company'] . '.']);
        }
    }

    /** Pago semanal del patrocinador (se llama desde la actualización económica) */
    public static function payWeekly(array $team, int $season, int $week): void
    {
        $teamId = (int)$team['id'];
        $c = self::activeContract($teamId, $season);
        if (!$c) {
            // Equipo sin patrocinador ni ofertas (p. ej. tras un reinicio manual): se le generan ofertas
            if (!DB::value("SELECT COUNT(*) FROM sponsor_contracts WHERE team_id = ? AND status = 'offer'", [$teamId])) {
                self::generateOffers($teamId, $season);
                $c = self::activeContract($teamId, $season);
            }
            if (!$c) {
                return;
            }
        }
        Economy::transaction($teamId, (int)$c['weekly_amount'], 'sponsors', 'Patrocinio: ' . $c['company'], $season, $week);
        DB::exec('UPDATE sponsor_contracts SET total_paid = total_paid + ? WHERE id = ?', [$c['weekly_amount'], $c['id']]);
    }

    /** Prima por victoria en liga (se llama desde el motor de partidos) */
    public static function winBonus(int $teamId, int $season, int $week): void
    {
        $c = self::activeContract($teamId, $season);
        if ($c && (int)$c['win_bonus'] > 0) {
            Economy::transaction($teamId, (int)$c['win_bonus'], 'sponsors', 'Prima por victoria: ' . $c['company'], $season, $week);
            DB::exec('UPDATE sponsor_contracts SET total_paid = total_paid + ? WHERE id = ?', [$c['win_bonus'], $c['id']]);
        }
    }

    /** Fin de temporada: premio por título y cierre de contratos que vencen */
    public static function seasonEnd(int $season, array $champions): void
    {
        foreach ($champions as $teamId) {
            $c = self::activeContract((int)$teamId, $season);
            if ($c && (int)$c['title_bonus'] > 0) {
                Economy::transaction((int)$teamId, (int)$c['title_bonus'], 'sponsors', 'Premio por el título: ' . $c['company'], $season);
                DB::exec('UPDATE sponsor_contracts SET total_paid = total_paid + ? WHERE id = ?', [$c['title_bonus'], $c['id']]);
                $t = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$teamId]);
                if ($t && !(int)$t['is_bot']) {
                    DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'sponsor',
                        'message' => '🏆 ' . $c['company'] . ' te paga ' . money($c['title_bonus']) . ' por ganar el título.']);
                }
            }
        }
        DB::exec("UPDATE sponsor_contracts SET status = 'ended' WHERE status = 'active' AND season_to <= ?", [$season]);
        DB::exec("UPDATE sponsor_contracts SET status = 'rejected' WHERE status = 'offer'");
    }

    /** Ofertas para todos los equipos que no tengan contrato en la temporada indicada */
    public static function generateSeasonOffers(int $season): void
    {
        if (!(int)DB::value('SELECT COUNT(*) FROM companies')) {
            return;
        }
        $teams = DB::all(
            "SELECT t.id FROM teams t WHERE t.is_national = 0 AND t.league_unit_id IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM sponsor_contracts sc WHERE sc.team_id = t.id AND sc.status = 'active' AND sc.season_from <= ? AND sc.season_to >= ?)",
            [$season, $season]
        );
        foreach (array_chunk($teams, 50) as $chunk) {
            DB::transaction(function () use ($chunk, $season) {
                foreach ($chunk as $t) {
                    self::generateOffers((int)$t['id'], $season);
                }
            });
        }
    }

    /** Un club cambia de dueño: termina su contrato y recibe ofertas nuevas */
    public static function resetForNewOwner(int $teamId): void
    {
        DB::exec("UPDATE sponsor_contracts SET status = 'ended' WHERE team_id = ? AND status = 'active'", [$teamId]);
        self::generateOffers($teamId);
    }
}
