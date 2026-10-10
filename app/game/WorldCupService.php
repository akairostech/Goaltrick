<?php
declare(strict_types=1);

/**
 * Mundial de fútbol entre selecciones nacionales.
 *
 * Formato (16 selecciones): 4 grupos de 4 a una vuelta (3 jornadas), pasan los dos primeros;
 * cuartos de final, semifinales, partido por el tercer puesto y final. Las eliminatorias que
 * terminan en empate se deciden por penaltis. Se juega en campo neutral, una jornada por semana
 * (config.ini -> [worldcup]).
 */
final class WorldCupService
{
    public const STAGE_NAMES = [
        'A' => 'Grupo A', 'B' => 'Grupo B', 'C' => 'Grupo C', 'D' => 'Grupo D',
        'E' => 'Grupo E', 'F1' => 'Grupo F', 'G' => 'Grupo G', 'H' => 'Grupo H',
        'R16' => 'Octavos de final', 'QF' => 'Cuartos de final', 'SF' => 'Semifinales', '3P' => 'Tercer puesto', 'F' => 'Final',
    ];

    public static function enabled(): bool
    {
        return Config::bool('worldcup', 'enabled', true);
    }

    public static function forSeason(int $season): ?array
    {
        return DB::row('SELECT * FROM worldcups WHERE season = ?', [$season]);
    }

    public static function stageName(?string $stage): string
    {
        return isset(self::STAGE_NAMES[$stage ?? '']) ? t(self::STAGE_NAMES[$stage]) : (string)$stage;
    }

    /** Número de grupos según las selecciones disponibles (16 → 4 grupos, 8 → 2 grupos) */
    private static function groupCount(int $nations): int
    {
        return $nations >= 32 ? 8 : ($nations >= 16 ? 4 : ($nations >= 8 ? 2 : 0));
    }

    /**
     * Sortea y programa el Mundial de una temporada.
     * $minWeek permite empezar más tarde (por ejemplo al actualizar a mitad de temporada).
     */
    public static function scheduleForSeason(int $season, int $startMonday, int $minWeek = 1): ?int
    {
        if (!self::enabled() || self::forSeason($season)) {
            return null;
        }
        $every = max(1, Config::int('worldcup', 'every_seasons', 1));
        if (($season - 1) % $every !== 0) {
            return null;
        }
        $nations = DB::all('SELECT t.*, c.code FROM teams t JOIN countries c ON c.id = t.country_id WHERE t.is_national = 1');
        $groups = self::groupCount(count($nations));
        if (!$groups) {
            return null;
        }
        $rounds = 3 + (int)round(log($groups * 2, 2));   // grupos + eliminatorias (2 grupos: SF+F, 4: QF+SF+F, 8: R16+QF+SF+F)
        $seasonWeeks = max(LeagueService::LEAGUE_ROUNDS, Config::int('game', 'season_weeks', 16));
        $startWeek = max(1, Config::int('worldcup', 'start_week', 3), $minWeek);
        if ($startWeek + $rounds - 1 > $seasonWeeks) {
            return null;
        }

        // Bombos por fuerza de la selección (suma del TSI de sus convocados)
        foreach ($nations as &$n) {
            $n['strength'] = NationService::strength((int)$n['country_id']);
        }
        unset($n);
        usort($nations, fn($a, $b) => $b['strength'] <=> $a['strength']);
        $nations = array_slice($nations, 0, $groups * 4);
        $letters = self::letters($groups);

        $weekday = Config::int('worldcup', 'weekday', 2);
        $time = (string)Config::get('worldcup', 'time', '20:00');
        $roundTime = function (int $r) use ($startMonday, $startWeek, $weekday, $time) {
            $week = $startWeek + $r - 1;
            $ws = strtotime('+' . (($week - 1) * 7) . ' days', $startMonday);
            return [$week, LeagueService::eventTime($ws, $weekday, $time)];
        };

        return DB::transaction(function () use ($season, $nations, $groups, $letters, $rounds, $roundTime) {
            $cupId = DB::insert('worldcups', ['season' => $season, 'status' => 'groups', 'created_at' => now()]);
            $byGroup = array_fill_keys($letters, []);
            for ($pot = 0; $pot < 4; $pot++) {
                $potTeams = array_slice($nations, $pot * $groups, $groups);
                shuffle($potTeams);
                foreach ($letters as $i => $letter) {
                    $t = $potTeams[$i];
                    $byGroup[$letter][] = (int)$t['id'];
                    DB::insert('worldcup_teams', ['cup_id' => $cupId, 'team_id' => $t['id'], 'group_letter' => $letter, 'pot' => $pot + 1]);
                }
            }
            for ($r = 1; $r <= $rounds; $r++) {
                [$week, $at] = $roundTime($r);
                DB::insert('scheduled_events', ['type' => 'worldcup_round', 'run_at' => $at, 'season' => $season, 'week' => $week, 'round' => $r]);
            }
            foreach ($byGroup as $letter => $teams) {
                foreach (LeagueService::roundRobin($teams) as $r => $pairs) {
                    if ($r > 3) {
                        break; // una sola vuelta
                    }
                    [$week, $at] = $roundTime($r);
                    foreach ($pairs as [$h, $a]) {
                        DB::insert('matches', [
                            'season' => $season, 'week' => $week, 'round' => $r, 'type' => 'worldcup',
                            'cup_id' => $cupId, 'cup_stage' => $letter, 'home_team_id' => $h, 'away_team_id' => $a,
                            'scheduled_at' => $at,
                        ]);
                    }
                }
            }
            return $cupId;
        });
    }

    /**
     * Si el Mundial de la temporada todavía no ha empezado y hay más selecciones disponibles que
     * participantes (por ejemplo tras añadir naciones nuevas), se vuelve a sortear con todas.
     * Devuelve true si se ha rehecho.
     */
    public static function rebuildIfNotStarted(int $season): bool
    {
        return self::rebuild($season, false);
    }

    /**
     * Vuelve a sortear el Mundial de la temporada con todas las selecciones si le faltan participantes.
     * Con $restartStarted también se rehace un Mundial ya empezado (pero no terminado): se anulan sus
     * partidos jugados (y las internacionalidades y goles con la selección que dieron) y empieza de cero.
     */
    public static function rebuild(int $season, bool $restartStarted = false): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $nations = (int)DB::value('SELECT COUNT(*) FROM teams WHERE is_national = 1');
        $wanted = self::groupCount($nations) * 4;
        $cup = self::forSeason($season);
        if ($cup) {
            $have = (int)DB::value('SELECT COUNT(*) FROM worldcup_teams WHERE cup_id = ?', [$cup['id']]);
            $played = (int)DB::value("SELECT COUNT(*) FROM matches WHERE type = 'worldcup' AND cup_id = ? AND status = 'played'", [$cup['id']]);
            if ($have >= $wanted || $cup['status'] === 'finished' || ($played > 0 && !$restartStarted)) {
                return false;
            }
        }
        $seasonStart = strtotime(GameState::get('season_start') ?? date('Y-m-d', LeagueService::firstSeasonStart()));
        $minWeek = GameState::week() + 1;
        try {
            return DB::transaction(function () use ($cup, $season, $seasonStart, $minWeek) {
                if ($cup) {
                    // Deshacer las estadísticas internacionales de los partidos que se anulan
                    foreach (DB::all("SELECT mp.player_id, mp.goals FROM match_players mp JOIN matches m ON m.id = mp.match_id
                                      WHERE m.type = 'worldcup' AND m.cup_id = ? AND m.status = 'played'", [$cup['id']]) as $r) {
                        DB::exec('UPDATE players SET caps = GREATEST(caps, 1) - 1, goals_national = GREATEST(goals_national, ?) - ? WHERE id = ?',
                            [(int)$r['goals'], (int)$r['goals'], $r['player_id']]);
                    }
                    DB::exec("DELETE mp FROM match_players mp JOIN matches m ON m.id = mp.match_id WHERE m.type = 'worldcup' AND m.cup_id = ?", [$cup['id']]);
                    DB::exec("DELETE FROM matches WHERE type = 'worldcup' AND cup_id = ?", [$cup['id']]);
                    DB::exec('DELETE FROM worldcup_teams WHERE cup_id = ?', [$cup['id']]);
                    DB::exec("DELETE FROM scheduled_events WHERE type = 'worldcup_round' AND season = ? AND status = 'pending'", [$season]);
                    DB::exec('DELETE FROM worldcups WHERE id = ?', [$cup['id']]);
                }
                if (!self::scheduleForSeason($season, $seasonStart, $minWeek)) {
                    throw new RuntimeException('no cabe');   // se deshace todo y se conserva el Mundial anterior
                }
                return true;
            });
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /** Juega una jornada del Mundial y prepara la siguiente fase */
    public static function playRound(int $season, int $round): string
    {
        $cup = self::forSeason($season);
        if (!$cup) {
            return tx('No hay Mundial esta temporada');
        }
        $ids = DB::all("SELECT id FROM matches WHERE type = 'worldcup' AND cup_id = ? AND round = ? AND status = 'scheduled'", [$cup['id'], $round]);
        foreach ($ids as $m) {
            MatchEngine::play((int)$m['id']);
        }
        $groups = (int)DB::value('SELECT COUNT(DISTINCT group_letter) FROM worldcup_teams WHERE cup_id = ?', [$cup['id']]);
        $letters = self::letters($groups);
        $label = tx('Mundial: jornada {round} ({n} partidos)', ['round' => $round, 'n' => count($ids)]);
        if (!$ids) {
            return $label; // ya se había jugado (no repetir el paso de fase)
        }

        if ($round === 3) {
            // Fin de la fase de grupos
            $q = [];
            foreach ($letters as $l) {
                $table = self::groupTable((int)$cup['id'], $l);
                $q[$l] = [$table[0]['team_id'] ?? null, $table[1]['team_id'] ?? null];
            }
            // Cruces: 1º de un grupo contra 2º del grupo vecino, en dos mitades del cuadro
            $pairs = [];
            for ($i = 0; $i < $groups; $i += 2) {
                $pairs[] = [$q[$letters[$i]][0], $q[$letters[$i + 1]][1]];
            }
            for ($i = 0; $i < $groups; $i += 2) {
                $pairs[] = [$q[$letters[$i + 1]][0], $q[$letters[$i]][1]];
            }
            $first = match ($groups) { 8 => 'R16', 4 => 'QF', default => 'SF' };
            self::createKnockout($cup, 4, $first, $pairs);
            DB::exec("UPDATE worldcups SET status = 'knockout' WHERE id = ?", [$cup['id']]);
            $label = tx_join($label, tx('· fin de la fase de grupos'));
        } elseif ($round > 3) {
            $played = DB::all("SELECT * FROM matches WHERE type = 'worldcup' AND cup_id = ? AND round = ? ORDER BY id", [$cup['id'], $round]);
            $stage = $played[0]['cup_stage'] ?? '';
            if ($stage === 'R16' || $stage === 'QF') {
                $w = array_map([self::class, 'winner'], $played);
                $pairs = [];
                for ($i = 0; $i + 1 < count($w); $i += 2) {
                    $pairs[] = [$w[$i], $w[$i + 1]];
                }
                self::createKnockout($cup, $round + 1, $stage === 'R16' ? 'QF' : 'SF', $pairs);
            } elseif ($stage === 'SF') {
                $w = array_map([self::class, 'winner'], $played);
                $l = array_map([self::class, 'loser'], $played);
                self::createKnockout($cup, $round + 1, '3P', [[$l[0], $l[1]]]);
                self::createKnockout($cup, $round + 1, 'F', [[$w[0], $w[1]]]);
            } else {
                $final = null;
                $third = null;
                foreach ($played as $m) {
                    if ($m['cup_stage'] === 'F') {
                        $final = $m;
                    } elseif ($m['cup_stage'] === '3P') {
                        $third = $m;
                    }
                }
                if ($final) {
                    $champ = self::winner($final);
                    DB::update('worldcups', [
                        'status' => 'finished', 'champion_team_id' => $champ, 'runnerup_team_id' => self::loser($final),
                        'third_team_id' => $third ? self::winner($third) : null,
                    ], 'id = ?', [$cup['id']]);
                    $name = DB::value('SELECT name FROM teams WHERE id = ?', [$champ]);
                    DB::insert('announcements', [
                        'user_id' => null,
                        'title' => tx('🏆 ¡{team}, campeón del Mundial de la temporada {season}!', ['team' => tx((string)$name), 'season' => $season]),
                        'body' => tx('{team} ha ganado la final del Mundial: {score}. Consulta todos los resultados en la sección «Mundial».', ['team' => tx((string)$name), 'score' => self::scoreText($final)]),
                        'created_at' => now(),
                    ]);
                    $label = tx_join($label, tx('· ¡{team} campeón!', ['team' => tx((string)$name)]));
                }
            }
        }
        return $label;
    }

    private static function createKnockout(array $cup, int $round, string $stage, array $pairs): void
    {
        $ev = DB::row("SELECT * FROM scheduled_events WHERE type = 'worldcup_round' AND season = ? AND round = ?", [$cup['season'], $round]);
        if (!$ev) {
            return;
        }
        foreach ($pairs as [$h, $a]) {
            if (!$h || !$a) {
                continue;
            }
            DB::insert('matches', [
                'season' => $cup['season'], 'week' => $ev['week'], 'round' => $round, 'type' => 'worldcup',
                'cup_id' => $cup['id'], 'cup_stage' => $stage, 'home_team_id' => $h, 'away_team_id' => $a,
                'scheduled_at' => $ev['run_at'],
            ]);
        }
    }

    /** Letras de los grupos (la F se guarda como «F1» para no confundirla con la final) */
    public static function letters(int $groups): array
    {
        return array_slice(['A', 'B', 'C', 'D', 'E', 'F1', 'G', 'H'], 0, $groups);
    }

    public static function letterLabel(string $l): string
    {
        return $l === 'F1' ? 'F' : $l;
    }

    public static function winner(array $m): int
    {
        $h = (int)$m['home_goals'] + ((int)($m['home_pens'] ?? 0) > (int)($m['away_pens'] ?? 0) ? 0.5 : 0);
        $a = (int)$m['away_goals'] + ((int)($m['away_pens'] ?? 0) > (int)($m['home_pens'] ?? 0) ? 0.5 : 0);
        return $h >= $a ? (int)$m['home_team_id'] : (int)$m['away_team_id'];
    }

    public static function loser(array $m): int
    {
        return self::winner($m) === (int)$m['home_team_id'] ? (int)$m['away_team_id'] : (int)$m['home_team_id'];
    }

    public static function scoreText(array $m): string
    {
        $t = (int)$m['home_goals'] . ' - ' . (int)$m['away_goals'];
        if ($m['home_pens'] !== null && $m['home_pens'] !== '') {
            $t .= ' (' . (int)$m['home_pens'] . '-' . (int)$m['away_pens'] . ' pen.)';
        }
        return $t;
    }

    /** Clasificación de un grupo */
    public static function groupTable(int $cupId, string $letter): array
    {
        $teams = DB::all('SELECT t.id, t.name, t.country_id, c.code FROM worldcup_teams w JOIN teams t ON t.id = w.team_id
            JOIN countries c ON c.id = t.country_id WHERE w.cup_id = ? AND w.group_letter = ? ORDER BY w.pot', [$cupId, $letter]);
        $table = [];
        foreach ($teams as $t) {
            $table[(int)$t['id']] = ['team_id' => (int)$t['id'], 'name' => $t['name'], 'country_id' => (int)$t['country_id'], 'code' => $t['code'],
                'pj' => 0, 'g' => 0, 'e' => 0, 'p' => 0, 'gf' => 0, 'gc' => 0, 'pts' => 0];
        }
        $matches = DB::all("SELECT * FROM matches WHERE type = 'worldcup' AND cup_id = ? AND cup_stage = ? AND status = 'played'", [$cupId, $letter]);
        foreach ($matches as $m) {
            $h = (int)$m['home_team_id'];
            $a = (int)$m['away_team_id'];
            if (!isset($table[$h], $table[$a])) {
                continue;
            }
            foreach ([[$h, (int)$m['home_goals'], (int)$m['away_goals']], [$a, (int)$m['away_goals'], (int)$m['home_goals']]] as [$t, $f, $c]) {
                $table[$t]['pj']++;
                $table[$t]['gf'] += $f;
                $table[$t]['gc'] += $c;
                if ($f > $c) {
                    $table[$t]['g']++;
                    $table[$t]['pts'] += 3;
                } elseif ($f === $c) {
                    $table[$t]['e']++;
                    $table[$t]['pts']++;
                } else {
                    $table[$t]['p']++;
                }
            }
        }
        $table = array_values($table);
        usort($table, fn($x, $y) => [$y['pts'], $y['gf'] - $y['gc'], $y['gf'], $x['name']] <=> [$x['pts'], $x['gf'] - $x['gc'], $x['gf'], $y['name']]);
        foreach ($table as $i => &$row) {
            $row['pos'] = $i + 1;
        }
        return $table;
    }

    /** Títulos mundiales por selección: [team_id => n] */
    public static function titles(): array
    {
        return array_map('intval', array_column(DB::all(
            "SELECT champion_team_id AS t, COUNT(*) n FROM worldcups WHERE status = 'finished' GROUP BY champion_team_id"
        ), 'n', 't'));
    }
}
