<?php
declare(strict_types=1);

/**
 * Ligas: creación del mundo, calendario de temporada, clasificaciones,
 * ascensos/descensos y premios.
 */
final class LeagueService
{
    public const LEAGUE_ROUNDS = 14;

    public static function roman(int $n): string
    {
        $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $r = '';
        foreach ($map as $v => $s) {
            while ($n >= $v) {
                $r .= $s;
                $n -= $v;
            }
        }
        return $r;
    }

    /** Crea las ligas de todos los países y sus selecciones nacionales */
    public static function createWorld(?callable $progress = null): void
    {
        foreach (DB::all('SELECT * FROM countries ORDER BY id') as $c) {
            if ((int)DB::value('SELECT COUNT(*) FROM league_units WHERE country_id = ?', [$c['id']])) {
                continue;
            }
            if ($progress) {
                $progress(t('Creando la liga de {country}...', ['country' => t($c['name'])]));
            }
            self::createCountryLeague((int)$c['id'], self::countryDivisions((int)$c['id']));
        }
        NationService::createNationalTeams();
    }

    /** Número de divisiones con las que se crea la liga de un país */
    public static function countryDivisions(int $countryId): int
    {
        $n = $countryId === 1 ? Config::int('game', 'divisions', 3) : Config::int('game', 'other_country_divisions', 2);
        return max(1, min(6, $n));
    }

    /** Crea la pirámide de series de un país (1 serie en la I, 4 en la II, 16 en la III...) con 8 equipos cada una */
    public static function createCountryLeague(int $countryId, int $divs): void
    {
        $prev = [];
        for ($level = 1; $level <= $divs; $level++) {
            $count = 4 ** ($level - 1);
            $cur = [];
            for ($n = 1; $n <= $count; $n++) {
                $parent = $level > 1 ? $prev[(int)ceil($n / 4)] : null;
                $name = $level === 1 ? 'I' : self::roman($level) . '.' . $n;
                $unitId = DB::insert('league_units', [
                    'country_id' => $countryId, 'level' => $level, 'number' => $n, 'name' => $name, 'parent_id' => $parent,
                ]);
                $cur[$n] = $unitId;
                DB::transaction(function () use ($unitId, $level, $countryId) {
                    for ($i = 0; $i < 8; $i++) {
                        TeamService::createBotTeam($unitId, $level, $countryId);
                    }
                });
            }
            $prev = $cur;
        }
    }

    /** Lunes 00:00 de la semana en la que empieza la temporada */
    public static function firstSeasonStart(): int
    {
        $today = strtotime('today');
        $dow = (int)date('N', $today);
        $monday = strtotime('-' . ($dow - 1) . ' days', $today);
        // De lunes a jueves empezamos esta misma semana; si no, el lunes siguiente
        return $dow <= 4 ? $monday : strtotime('+7 days', $monday);
    }

    public static function eventTime(int $weekStart, int $weekday, string $time): string
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':0'));
        $ts = strtotime('+' . ($weekday - 1) . ' days', $weekStart);
        return date('Y-m-d H:i:s', mktime($h, $m, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts)));
    }

    public static function leagueTime(int $weekStart): string
    {
        return self::eventTime($weekStart, Config::int('game', 'league_weekday', 6), (string)Config::get('game', 'league_time', '18:00'));
    }

    /** Crea calendario de partidos y eventos de toda una temporada */
    public static function createSeason(int $season, int $startMonday): void
    {
        $weeks = max(self::LEAGUE_ROUNDS, Config::int('game', 'season_weeks', 16));
        GameState::set('season', $season);
        GameState::set('week', 1);
        GameState::set('season_start', date('Y-m-d H:i:s', $startMonday));
        if (!GameState::get('last_training_at')) {
            GameState::set('last_training_at', date('Y-m-d H:i:s', $startMonday));
        }

        DB::transaction(function () use ($season, $startMonday, $weeks) {
            for ($w = 1; $w <= $weeks; $w++) {
                $ws = strtotime('+' . (($w - 1) * 7) . ' days', $startMonday);
                $events = [
                    ['friendly_round', self::eventTime($ws, Config::int('game', 'friendly_weekday', 3), (string)Config::get('game', 'friendly_time', '20:00')), null],
                    ['training', self::eventTime($ws, Config::int('game', 'training_weekday', 4), (string)Config::get('game', 'training_time', '06:00')), null],
                    ['economy', self::eventTime($ws, Config::int('game', 'economy_weekday', 5), (string)Config::get('game', 'economy_time', '06:00')), null],
                ];
                if ($w <= self::LEAGUE_ROUNDS) {
                    $events[] = ['league_round', self::leagueTime($ws), $w];
                }
                foreach ($events as [$type, $at, $round]) {
                    DB::insert('scheduled_events', ['type' => $type, 'run_at' => $at, 'season' => $season, 'week' => $w, 'round' => $round]);
                }
            }
            $end = strtotime('+' . ($weeks * 7) . ' days', $startMonday) - 3600; // domingo 23:00 de la última semana
            DB::insert('scheduled_events', ['type' => 'season_end', 'run_at' => date('Y-m-d H:i:s', $end), 'season' => $season, 'week' => $weeks, 'round' => null]);

            self::createFixtures($season, $startMonday, array_column(DB::all('SELECT id FROM league_units'), 'id'));
        });
        WorldCupService::scheduleForSeason($season, $startMonday);
        CupService::createForSeason($season);
        SponsorService::generateSeasonOffers($season);
    }

    /** Calendario de liga (14 jornadas) de las series indicadas */
    public static function createFixtures(int $season, int $startMonday, array $unitIds): void
    {
        foreach ($unitIds as $unitId) {
            $teams = array_map('intval', array_column(DB::all('SELECT id FROM teams WHERE league_unit_id = ? AND is_national = 0', [$unitId]), 'id'));
            if (count($teams) < 2) {
                continue;
            }
            shuffle($teams);
            foreach (self::roundRobin($teams) as $round => $pairs) {
                $ws = strtotime('+' . (($round - 1) * 7) . ' days', $startMonday);
                foreach ($pairs as [$h, $a]) {
                    DB::insert('matches', [
                        'season' => $season, 'week' => $round, 'round' => $round, 'type' => 'league',
                        'league_unit_id' => $unitId, 'home_team_id' => $h, 'away_team_id' => $a,
                        'scheduled_at' => self::leagueTime($ws),
                    ]);
                }
            }
        }
    }

    /** Liga a doble vuelta (método del círculo). Devuelve [ronda => [[local, visitante], ...]] */
    public static function roundRobin(array $teams): array
    {
        if (count($teams) % 2) {
            $teams[] = null;
        }
        $n = count($teams);
        $rounds = [];
        $list = $teams;
        for ($r = 0; $r < $n - 1; $r++) {
            $pairs = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $list[$i];
                $b = $list[$n - 1 - $i];
                if ($a === null || $b === null) {
                    continue;
                }
                $pairs[] = ($r + $i) % 2 ? [$a, $b] : [$b, $a];
            }
            $rounds[$r + 1] = $pairs;
            // rotar manteniendo el primero fijo
            $fixed = array_shift($list);
            $last = array_pop($list);
            array_unshift($list, $last);
            array_unshift($list, $fixed);
        }
        $first = count($rounds);
        foreach ($rounds as $r => $pairs) {
            $rounds[$r + $first] = array_map(fn($p) => [$p[1], $p[0]], $pairs);
        }
        return $rounds;
    }

    /** Clasificación de una serie en una temporada */
    public static function standings(int $unitId, int $season): array
    {
        $teams = DB::all('SELECT id, name, user_id, is_bot FROM teams WHERE league_unit_id = ?', [$unitId]);
        // Si la temporada ya terminó, los equipos pueden haber cambiado de serie: los sacamos de los partidos
        $ids = array_map('intval', array_column(DB::all(
            "SELECT DISTINCT home_team_id AS id FROM matches WHERE league_unit_id = ? AND season = ? AND type = 'league'",
            [$unitId, $season]
        ), 'id'));
        if ($ids) {
            $teams = DB::all('SELECT id, name, user_id, is_bot FROM teams WHERE id IN (' . implode(',', $ids) . ')');
        }
        $table = [];
        foreach ($teams as $t) {
            $table[(int)$t['id']] = ['team_id' => (int)$t['id'], 'name' => $t['name'], 'is_bot' => (int)$t['is_bot'],
                'pj' => 0, 'g' => 0, 'e' => 0, 'p' => 0, 'gf' => 0, 'gc' => 0, 'pts' => 0, 'form' => []];
        }
        $matches = DB::all(
            "SELECT home_team_id, away_team_id, home_goals, away_goals FROM matches
             WHERE league_unit_id = ? AND season = ? AND type = 'league' AND status = 'played' ORDER BY round",
            [$unitId, $season]
        );
        foreach ($matches as $m) {
            $h = (int)$m['home_team_id'];
            $a = (int)$m['away_team_id'];
            if (!isset($table[$h], $table[$a])) {
                continue;
            }
            $hg = (int)$m['home_goals'];
            $ag = (int)$m['away_goals'];
            foreach ([[$h, $hg, $ag], [$a, $ag, $hg]] as [$t, $f, $c]) {
                $table[$t]['pj']++;
                $table[$t]['gf'] += $f;
                $table[$t]['gc'] += $c;
                if ($f > $c) {
                    $table[$t]['g']++;
                    $table[$t]['pts'] += 3;
                    $table[$t]['form'][] = 'V';
                } elseif ($f === $c) {
                    $table[$t]['e']++;
                    $table[$t]['pts'] += 1;
                    $table[$t]['form'][] = 'E';
                } else {
                    $table[$t]['p']++;
                    $table[$t]['form'][] = 'D';
                }
            }
        }
        $table = array_values($table);
        usort($table, fn($x, $y) => [$y['pts'], $y['gf'] - $y['gc'], $y['gf'], $x['name']] <=> [$x['pts'], $x['gf'] - $x['gc'], $x['gf'], $y['name']]);
        foreach ($table as $i => &$row) {
            $row['pos'] = $i + 1;
            $row['form'] = array_slice($row['form'], -5);
        }
        return $table;
    }

    /** Fin de temporada: ascensos, descensos, premios, retiradas y nueva temporada */
    public static function seasonEnd(int $season): void
    {
        $divs = Config::int('game', 'divisions', 3);
        $units = DB::all('SELECT * FROM league_units ORDER BY level, number');
        $final = [];
        foreach ($units as $u) {
            $final[(int)$u['id']] = self::standings((int)$u['id'], $season);
        }
        $moves = []; // team_id => new unit
        $result = [];
        foreach ($units as $u) {
            $children = array_values(array_filter($units, fn($c) => (int)$c['parent_id'] === (int)$u['id']));
            if (!$children) {
                continue;
            }
            $champions = [];
            foreach ($children as $c) {
                $st = $final[(int)$c['id']];
                if ($st) {
                    $champions[] = $st[0] + ['unit' => (int)$c['id']];
                }
            }
            usort($champions, fn($x, $y) => [$y['pts'], $y['gf'] - $y['gc'], $y['gf']] <=> [$x['pts'], $x['gf'] - $x['gc'], $x['gf']]);
            $st = $final[(int)$u['id']];
            $relegated = array_slice($st, -2);
            $promoted = array_slice($champions, 0, count($relegated));
            foreach ($promoted as $i => $p) {
                $down = $relegated[$i] ?? null;
                if (!$down) {
                    continue;
                }
                $moves[$p['team_id']] = (int)$u['id'];
                $moves[$down['team_id']] = $p['unit'];
                $result[$p['team_id']] = 'promoted';
                $result[$down['team_id']] = 'relegated';
            }
        }

        $ts = now();
        DB::transaction(function () use ($units, $final, $moves, $result, $season, $divs, $ts) {
            foreach ($units as $u) {
                $level = (int)$u['level'];
                $prizeF = 1 + max(0, $divs - $level) * 0.5;
                foreach ($final[(int)$u['id']] as $row) {
                    $tid = $row['team_id'];
                    $res = $result[$tid] ?? ($row['pos'] === 1 ? 'champion' : 'stay');
                    if ($row['pos'] === 1 && $res === 'promoted') {
                        $res = 'champion';
                    }
                    DB::insert('season_history', [
                        'team_id' => $tid, 'season' => $season, 'league_unit_id' => $u['id'], 'position' => $row['pos'],
                        'points' => $row['pts'], 'goals_for' => $row['gf'], 'goals_against' => $row['gc'], 'result' => $res,
                    ]);
                    $prize = (int)round(Config::int('economy', 'prize_' . $row['pos'], 0) * $prizeF);
                    Economy::transaction($tid, $prize, 'prizes', tx('Premio por terminar {pos}º en la serie {series}', ['pos' => $row['pos'], 'series' => $u['name']]), $season);
                    $team = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$tid]);
                    if ($team && !(int)$team['is_bot']) {
                        $msg = tx('🏆 Temporada {season} terminada: posición {pos} en la serie {series} con {pts} puntos.', ['season' => $season, 'pos' => $row['pos'], 'series' => $u['name'], 'pts' => $row['pts']]);
                        if (isset($moves[$tid])) {
                            $newName = DB::value('SELECT name FROM league_units WHERE id = ?', [$moves[$tid]]);
                            $msg = tx_join($msg, $result[$tid] === 'promoted'
                                ? tx('¡ASCENSO a la serie {series}.', ['series' => $newName])
                                : tx('Descenso a la serie {series}.', ['series' => $newName]));
                        }
                        DB::insert('team_events', ['team_id' => $tid, 'created_at' => $ts, 'type' => 'season', 'message' => $msg]);
                    }
                }
            }
            foreach ($moves as $tid => $unit) {
                DB::exec('UPDATE teams SET league_unit_id = ? WHERE id = ?', [$unit, $tid]);
            }
            DB::exec('UPDATE players SET goals_season = 0, yellow_cards = 0');
        });

        // Patrocinio: premios por título y fin de los contratos que vencen
        $champions = [];
        foreach ($final as $rows) {
            if ($rows) {
                $champions[] = $rows[0]['team_id'];
            }
        }
        SponsorService::seasonEnd($season, $champions);

        self::retirements();
        StaffService::seasonEnd();
        CupService::seasonEnd($season);
        BotAI::fillSquads();
        DB::exec("UPDATE friendly_requests SET status = 'expired' WHERE status = 'pending'");

        // Siguiente temporada: el lunes siguiente
        // (si la temporada se adelantó desde la administración, la nueva empieza igualmente el próximo lunes)
        $nextMonday = strtotime('next monday', strtotime(date('Y-m-d', time())));
        self::createSeason($season + 1, $nextMonday);
    }

    /** Retiradas de jugadores veteranos */
    public static function retirements(): void
    {
        $ts = now();
        foreach (DB::all('SELECT p.*, t.is_bot FROM players p LEFT JOIN teams t ON t.id = p.team_id WHERE p.retired = 0 AND p.age_years >= 34') as $p) {
            $prob = 0.25 + ((int)$p['age_years'] - 34) * 0.2 - PlayerFactory::mainSkill($p) * 0.01;
            if (rnd() < $prob) {
                DB::exec('UPDATE players SET retired = 1, team_id = NULL WHERE id = ?', [$p['id']]);
                DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE player_id = ? AND status = 'active'", [$ts, $p['id']]);
                if ($p['team_id'] && !(int)$p['is_bot']) {
                    DB::insert('team_events', ['team_id' => $p['team_id'], 'created_at' => $ts, 'type' => 'retire',
                        'message' => tx('👋 {player} ({age} años) ha anunciado su retirada.', ['player' => player_name($p), 'age' => $p['age_years']])]);
                }
            }
        }
    }

    /**
     * Partidos de un equipo (como local o visitante). Usa UNION en lugar de OR para aprovechar los índices.
     * $where se aplica a la tabla matches (sin alias), $order al resultado (x.).
     */
    public static function teamMatches(int $teamId, string $where = '1=1', array $params = [], string $order = 'x.scheduled_at', int $limit = 500): array
    {
        $sql = "SELECT x.*, h.name AS home_name, a.name AS away_name, h.name AS hn, a.name AS an FROM (
                    (SELECT * FROM matches WHERE home_team_id = ? AND $where)
                    UNION ALL
                    (SELECT * FROM matches WHERE away_team_id = ? AND $where)
                ) x JOIN teams h ON h.id = x.home_team_id JOIN teams a ON a.id = x.away_team_id
                ORDER BY $order LIMIT " . (int)$limit;
        return DB::all($sql, array_merge([$teamId], $params, [$teamId], $params));
    }

    /** Próximo partido de un equipo */
    public static function nextMatch(int $teamId): ?array
    {
        return self::teamMatches($teamId, "status = 'scheduled'", [], 'x.scheduled_at', 1)[0] ?? null;
    }

    public static function lastMatch(int $teamId): ?array
    {
        return self::teamMatches($teamId, "status = 'played'", [], 'x.played_at DESC', 1)[0] ?? null;
    }
}
