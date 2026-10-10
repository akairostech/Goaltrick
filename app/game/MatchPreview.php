<?php
declare(strict_types=1);

/**
 * Previa de un partido: índice de rendimiento estimado, resumen de los dos equipos,
 * pronóstico (modelo de Poisson con el mismo esquema que el motor) y lo que opinan los socios.
 */
final class MatchPreview
{
    /** Ocasiones medias por partido en el motor */
    private const CHANCES = 10.5;

    public static function build(array $match, ?int $viewerTeamId): array
    {
        $home = DB::row('SELECT * FROM teams WHERE id = ?', [$match['home_team_id']]);
        $away = DB::row('SELECT * FROM teams WHERE id = ?', [$match['away_team_id']]);
        $own = [
            'home' => $viewerTeamId === (int)$home['id'],
            'away' => $viewerTeamId === (int)$away['id'],
        ];
        $r = MatchEngine::estimate($match, $home, $away, $own);
        $knockout = $match['type'] === 'cup'
            || ($match['type'] === 'worldcup' && in_array($match['cup_stage'], ['R16', 'QF', 'SF', '3P', 'F'], true));

        [$xh, $xa] = self::expectedGoals($r['home'], $r['away']);
        $grid = self::grid($xh, $xa);
        $probs = self::outcome($grid);
        $fans = [
            'home' => self::fanPoll($home, $xh, $xa, 'home'),
            'away' => self::fanPoll($away, $xa, $xh, 'away'),
        ];
        return [
            'teams' => ['home' => $home, 'away' => $away],
            'ratings' => $r,
            'index' => ['home' => self::index($r['home']), 'away' => self::index($r['away'])],
            'own' => $own,
            'xg' => ['home' => $xh, 'away' => $xa],
            'probs' => $probs,
            'score' => self::likelyScore($grid),
            'fans' => $fans,
            'knockout' => $knockout,
            'summary' => ['home' => self::summary($home, $match), 'away' => self::summary($away, $match)],
        ];
    }

    /** Índice de rendimiento 0..100 a partir de las 7 valoraciones */
    public static function index(array $r): int
    {
        $def = ($r['rdef'] + $r['cdef'] + $r['ldef']) / 3;
        $att = ($r['ratt'] + $r['catt'] + $r['latt']) / 3;
        return (int)max(1, min(100, round((0.34 * $r['mid'] + 0.33 * $def + 0.33 * $att) * 5)));
    }

    private static function prob(float $att, float $def): float
    {
        $a = pow(max(0.1, $att), 2.6);
        $d = pow(max(0.1, $def), 2.6);
        return $a / ($a + $d);
    }

    /** Goles esperados de cada equipo, imitando el reparto de ocasiones y su conversión en el motor */
    public static function expectedGoals(array $h, array $a): array
    {
        $ph = pow($h['mid'], 1.4) / (pow($h['mid'], 1.4) + pow($a['mid'], 1.4));
        $conv = function (array $att, array $def): float {
            $open = (self::prob($att['latt'], $def['rdef']) + self::prob($att['catt'], $def['cdef']) + self::prob($att['ratt'], $def['ldef'])) / 3;
            return 0.88 * $open * 0.88 + 0.12 * 0.7 * self::prob($att['catt'], $def['cdef']);
        };
        return [round(self::CHANCES * $ph * $conv($h, $a), 2), round(self::CHANCES * (1 - $ph) * $conv($a, $h), 2)];
    }

    private static function poisson(float $l, int $k): float
    {
        return exp(-$l) * pow($l, $k) / array_product(range(1, max(1, $k)));
    }

    /** Probabilidad de cada marcador (0..7 goles) */
    private static function grid(float $xh, float $xa): array
    {
        $g = [];
        for ($i = 0; $i <= 7; $i++) {
            for ($j = 0; $j <= 7; $j++) {
                $g[$i][$j] = self::poisson($xh, $i) * self::poisson($xa, $j);
            }
        }
        return $g;
    }

    private static function outcome(array $g): array
    {
        $o = ['home' => 0.0, 'draw' => 0.0, 'away' => 0.0];
        foreach ($g as $i => $row) {
            foreach ($row as $j => $p) {
                $o[$i > $j ? 'home' : ($i === $j ? 'draw' : 'away')] += $p;
            }
        }
        $sum = array_sum($o) ?: 1;
        return array_map(fn($v) => $v / $sum, $o);
    }

    private static function likelyScore(array $g): array
    {
        $best = [0, 0];
        $bp = -1;
        foreach ($g as $i => $row) {
            foreach ($row as $j => $p) {
                if ($p > $bp) {
                    $bp = $p;
                    $best = [$i, $j];
                }
            }
        }
        return $best;
    }

    /**
     * Encuesta entre los socios de un club: son optimistas si la afición está contenta y el equipo
     * tiene confianza. Devuelve sus porcentajes (victoria propia, empate, derrota) y el marcador más votado.
     */
    private static function fanPoll(array $team, float $xOwn, float $xOpp, string $side): array
    {
        $mood = (float)$team['fan_mood'];
        $conf = (float)$team['confidence'];
        $opt = clamp(($mood - 5) * 0.045 + ($conf - 5) * 0.03, -0.35, 0.45);
        $own = $xOwn * (1 + $opt);
        $opp = max(0.05, $xOpp * (1 - $opt * 0.7));
        $g = self::grid($own, $opp);
        $o = self::outcome($g);
        [$a, $b] = self::likelyScore($g);
        $win = $o['home'];
        $quote = match (true) {
            $win >= 0.62 => t('«¡Este partido está ganado!»'),
            $win >= 0.45 => t('«Confiamos en sacar los tres puntos.»'),
            $win >= 0.3 => t('«Va a ser un partido muy igualado.»'),
            $win >= 0.18 => t('«Será difícil, pero hay que creer.»'),
            default => t('«Firmaríamos un empate ahora mismo.»'),
        };
        return [
            'win' => $o['home'], 'draw' => $o['draw'], 'lose' => $o['away'],
            'score' => $side === 'home' ? [$a, $b] : [$b, $a],
            'voters' => max(12, (int)round((int)$team['fans'] * (0.15 + 0.02 * (float)$team['fan_mood']))),
            'quote' => $quote,
            'optimism' => $opt,
        ];
    }

    /** Resumen de un equipo para la previa */
    private static function summary(array $t, array $match): array
    {
        $id = (int)$t['id'];
        $season = (int)$match['season'];
        $s = [
            'manager' => $t['user_id'] ? (string)DB::value('SELECT username FROM users WHERE id = ?', [$t['user_id']]) : null,
            'unit' => null, 'level' => null, 'pos' => null, 'pts' => null, 'gf' => null, 'gc' => null,
            'form' => [],
        ];
        if ($t['league_unit_id']) {
            $u = DB::row('SELECT id, name, level FROM league_units WHERE id = ?', [$t['league_unit_id']]);
            $s['unit'] = $u;
            foreach (LeagueService::standings((int)$u['id'], $season) as $i => $row) {
                if ((int)$row['team_id'] === $id) {
                    $s['pos'] = $i + 1;
                    $s['pts'] = (int)$row['pts'];
                    $s['gf'] = (int)$row['gf'];
                    $s['gc'] = (int)$row['gc'];
                }
            }
        }
        foreach (LeagueService::teamMatches($id, "status = 'played'", [], 'x.played_at DESC', 5) as $m) {
            $home = (int)$m['home_team_id'] === $id;
            $d = $home ? (int)$m['home_goals'] - (int)$m['away_goals'] : (int)$m['away_goals'] - (int)$m['home_goals'];
            $s['form'][] = ['r' => $d > 0 ? 'w' : ($d < 0 ? 'l' : 'd'), 'id' => (int)$m['id'],
                'txt' => $m['home_name'] . ' ' . score_label($m) . ' ' . $m['away_name']];
        }
        if ((int)$t['is_national']) {
            $squad = NationService::squad((int)$t['country_id'], true);
            $s['players'] = count($squad);
            $s['age'] = $squad ? array_sum(array_column($squad, 'age_years')) / count($squad) : 0;
            $s['tsi'] = array_sum(array_column($squad, 'tsi'));
            $s['pform'] = $squad ? array_sum(array_column($squad, 'form')) / count($squad) : 0;
            usort($squad, fn($a, $b) => (int)$b['goals_national'] <=> (int)$a['goals_national']);
            $s['scorer'] = $squad && (int)$squad[0]['goals_national'] > 0 ? $squad[0] + ['g' => (int)$squad[0]['goals_national']] : null;
        } else {
            $q = DB::row('SELECT COUNT(*) n, AVG(age_years) age, SUM(tsi) tsi, AVG(form) form FROM players WHERE team_id = ? AND retired = 0', [$id]);
            $s['players'] = (int)$q['n'];
            $s['age'] = (float)$q['age'];
            $s['tsi'] = (int)$q['tsi'];
            $s['pform'] = (float)$q['form'];
            $sc = DB::row('SELECT id, first_name, last_name, avatar, goals_season FROM players WHERE team_id = ? AND retired = 0 AND goals_season > 0 ORDER BY goals_season DESC LIMIT 1', [$id]);
            $s['scorer'] = $sc ? $sc + ['g' => (int)$sc['goals_season']] : null;
            $tr = StaffService::trainer($id);
            $s['trainer'] = $tr;
        }
        $s['injured'] = (int)DB::value('SELECT COUNT(*) FROM players WHERE team_id = ? AND retired = 0 AND (injury_weeks > 0 OR suspended > 0)', [$id]);
        return $s;
    }
}
