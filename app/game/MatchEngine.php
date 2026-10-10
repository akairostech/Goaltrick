<?php
declare(strict_types=1);

/**
 * Motor de partidos.
 *
 * Cada equipo obtiene 7 valoraciones (medio campo, defensa der/cen/izq, ataque der/cen/izq)
 * a partir de las habilidades de los jugadores en su posición. El medio campo decide quién
 * tiene la posesión de cada ocasión; el ataque del sector frente a la defensa rival decide
 * si la ocasión termina en gol. Hay balón parado, tiros lejanos, contraataques,
 * eventos especiales por especialidad, tarjetas, lesiones y cansancio.
 */
final class MatchEngine
{
    private const SCALE = ['mid' => 0.30, 'rdef' => 0.45, 'cdef' => 0.28, 'ldef' => 0.45, 'ratt' => 0.44, 'catt' => 0.36, 'latt' => 0.44];
    public const SECTORS = ['mid' => 'Medio campo', 'rdef' => 'Defensa derecha', 'cdef' => 'Defensa central', 'ldef' => 'Defensa izquierda',
                            'ratt' => 'Ataque derecho', 'catt' => 'Ataque central', 'latt' => 'Ataque izquierdo'];

    private array $match;
    private array $team = [];
    private array $plan = [];
    private array $onPitch = [];     // side => pos => player
    private array $bench = [];       // side => slot => player
    private array $entered = [];     // side => pid => minute de entrada
    private array $left = [];        // side => pid => minute de salida
    private array $posOf = [];       // pid => posición (última)
    private array $goalsBy = [];     // pid => goles
    private array $yellows = [];     // pid => tarjetas amarillas en el partido
    private array $reds = [];        // pid => 1
    private array $injuries = [];    // pid => semanas
    private array $events = [];
    private array $score = ['home' => 0, 'away' => 0];
    private array $chances = ['home' => 0, 'away' => 0];
    private array $ratingSum = [];
    private array $ratingCount = ['home' => 0, 'away' => 0];
    private float $possSum = 0.0;
    private int $possN = 0;
    private array $cache = [];
    private array $version = ['home' => 0, 'away' => 0];
    private ?array $pens = null;
    private array $tactician = [];

    public static function play(int $matchId): ?array
    {
        $match = DB::row('SELECT * FROM matches WHERE id = ?', [$matchId]);
        if (!$match || $match['status'] === 'played') {
            return null;
        }
        $home = DB::row('SELECT * FROM teams WHERE id = ?', [$match['home_team_id']]);
        $away = DB::row('SELECT * FROM teams WHERE id = ?', [$match['away_team_id']]);
        if (!$home || !$away) {
            return null;
        }
        $engine = new self($match, $home, $away);
        $engine->simulate();
        return DB::transaction(fn() => $engine->persist());
    }

    /** Valoraciones estimadas con la alineación guardada (sin factor campo) */
    public static function preview(array $team): array
    {
        $engine = new self(['id' => 0, 'type' => 'league'], $team, $team);
        return $engine->ratings('away', 0);
    }

    /**
     * Valoraciones estimadas de los dos equipos para un partido concreto (con factor campo).
     * Si $ownLineup[side] es false se usa la mejor alineación automática en lugar de la que el
     * mánager haya enviado (así la previa no revela la alineación del rival).
     */
    public static function estimate(array $match, array $home, array $away, array $ownLineup = ['home' => false, 'away' => false]): array
    {
        foreach (['home', 'away'] as $side) {
            if (empty($ownLineup[$side])) {
                ${$side}['is_bot'] = 1;
            }
        }
        $engine = new self($match, $home, $away);
        return ['home' => $engine->ratings('home', 0), 'away' => $engine->ratings('away', 0)];
    }

    private function __construct(array $match, array $home, array $away)
    {
        $this->match = $match;
        $this->team = ['home' => $home, 'away' => $away];
        foreach (['home', 'away'] as $side) {
            $plan = Lineup::forMatch($this->team[$side], (int)($this->match['id'] ?? 0));
            $this->plan[$side] = $plan;
            $this->onPitch[$side] = $plan['starters'];
            $this->bench[$side] = $plan['bench'];
            $this->entered[$side] = [];
            $this->left[$side] = [];
            foreach ($plan['starters'] as $pos => $p) {
                $this->entered[$side][(int)$p['id']] = 0;
                $this->posOf[(int)$p['id']] = $pos;
            }
            $this->ratingSum[$side] = array_fill_keys(array_keys(self::SCALE), 0.0);
            $this->tactician[$side] = !(int)($this->team[$side]['is_national'] ?? 0) && StaffService::trainerPersonality((int)$this->team[$side]['id']) === 'tactico';
        }
    }

    /* ------------------------------------------------------------------
     *  Valoraciones
     * ------------------------------------------------------------------ */

    private function effSkill(array $p, string $skill, int $minute): float
    {
        $s = (float)$p[$skill];
        $s += min(2.0, log(1 + (float)$p['experience']) * 0.45);
        $form = 0.75 + (float)$p['form'] * 0.04;
        // Cansancio: a mayor resistencia, menor pérdida
        $loss = max(0.0, 0.24 - (float)$p['stamina'] * 0.019);
        $t = $minute <= 45 ? $minute / 90 : 0.35 + ($minute - 45) / 90 * 0.9;
        $tired = 1 - $loss * $t;
        return max(0.0, $s * $form * $tired);
    }

    public function ratings(string $side, int $minute): array
    {
        $key = $side . '|' . $this->version[$side] . '|' . intdiv($minute, 15);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $team = $this->team[$side];
        $plan = $this->plan[$side];
        $count = ['CD' => 0, 'IM' => 0, 'FW' => 0];
        foreach ($this->onPitch[$side] as $pos => $p) {
            $t = Lineup::POSITIONS[$pos][0];
            if (isset($count[$t])) {
                $count[$t]++;
            }
        }
        $crowd = [
            'CD' => [0 => 1, 1 => 1, 2 => 0.964, 3 => 0.90][$count['CD']],
            'IM' => [0 => 1, 1 => 1, 2 => 0.935, 3 => 0.825][$count['IM']],
            'FW' => [0 => 1, 1 => 1, 2 => 0.945, 3 => 0.865][$count['FW']],
        ];
        $r = array_fill_keys(array_keys(self::SCALE), 0.0);
        foreach ($this->onPitch[$side] as $pos => $p) {
            [$type, $sideP] = Lineup::POSITIONS[$pos];
            $order = $plan['orders'][$pos] ?? 'normal';
            $mult = $crowd[$type] ?? 1.0;
            foreach (Lineup::contributions($type, $sideP) as $sector => $weights) {
                $m = $mult;
                if ($order === 'offensive') {
                    $m *= str_ends_with($sector, 'att') ? 1.25 : (str_ends_with($sector, 'def') ? 0.7 : 0.95);
                } elseif ($order === 'defensive') {
                    $m *= str_ends_with($sector, 'def') ? 1.25 : (str_ends_with($sector, 'att') ? 0.7 : 0.95);
                }
                foreach ($weights as $skill => $w) {
                    $r[$sector] += $this->effSkill($p, $skill, $minute) * $w * $m;
                }
            }
        }
        // Modificadores de equipo
        $spirit = 0.8 + (float)$team['team_spirit'] * 0.04;
        $conf = 0.92 + (float)$team['confidence'] * 0.016;
        $r['mid'] *= $spirit;
        if (!empty($this->tactician[$side])) {
            $r['mid'] *= 1.02; // entrenador táctico
        }
        if ($side === 'home' && ($this->match['type'] ?? '') !== 'worldcup') {
            $r['mid'] *= 1.12;
        }
        $att = match ($plan['attitude']) { 'pic' => 0.84, 'mots' => 1.11, default => 1.0 };
        $r['mid'] *= $att;
        // Capitán
        $cap = $this->findOnPitch($side, (int)$plan['captain']);
        if ($cap) {
            $r['mid'] *= 1 + ((int)$cap['leadership'] - 4) * 0.008;
        }
        foreach (['ratt', 'catt', 'latt'] as $s) {
            $r[$s] *= $conf;
        }
        switch ($plan['tactic']) {
            case 'counter':
                $r['mid'] *= 0.93;
                break;
            case 'aim':
                $r['catt'] *= 1.1;
                $r['ratt'] *= 0.85;
                $r['latt'] *= 0.85;
                break;
            case 'aow':
                $r['ratt'] *= 1.1;
                $r['latt'] *= 1.1;
                $r['catt'] *= 0.85;
                break;
            case 'longshots':
                foreach (['ratt', 'catt', 'latt'] as $s) {
                    $r[$s] *= 0.95;
                }
                break;
        }
        // Jugar con menos de 11
        $missing = 11 - count($this->onPitch[$side]);
        if ($missing > 0) {
            $r['mid'] *= 1 - 0.04 * $missing;
        }
        foreach ($r as $k => $v) {
            $r[$k] = max(0.5, round($v * self::SCALE[$k], 2));
        }
        return $this->cache[$key] = $r;
    }

    private function findOnPitch(string $side, int $pid): ?array
    {
        foreach ($this->onPitch[$side] as $p) {
            if ((int)$p['id'] === $pid) {
                return $p;
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------
     *  Simulación
     * ------------------------------------------------------------------ */

    private function simulate(): void
    {
        $hn = $this->team['home']['name'];
        $an = $this->team['away']['name'];
        $this->event(0, 'info', null, tx('Comienza el partido entre {home} y {away}. ¡Rueda el balón!', ['home' => $hn, 'away' => $an]));
        $this->event(0, 'info', null, tx('{home} juega con táctica «{htactic}» y {away} con «{atactic}».', [
            'home' => $hn, 'htactic' => tx(TACTICS[$this->plan['home']['tactic']] ?? 'Normal'),
            'away' => $an, 'atactic' => tx(TACTICS[$this->plan['away']['tactic']] ?? 'Normal'),
        ]));
        foreach (['home', 'away'] as $side) {
            if ($this->plan[$side]['attitude'] === 'mots') {
                $this->event(0, 'info', $side, tx('{team} se toma este encuentro como el partido del siglo.', ['team' => $this->team[$side]['name']]));
            } elseif ($this->plan[$side]['attitude'] === 'pic') {
                $this->event(0, 'info', $side, tx('{team} parece tomarse el partido con mucha calma.', ['team' => $this->team[$side]['name']]));
            }
            if (count($this->onPitch[$side]) < 11) {
                $this->event(0, 'info', $side, tx('{team} sólo ha podido alinear {n} jugadores.', ['team' => $this->team[$side]['name'], 'n' => count($this->onPitch[$side])]));
            }
        }

        // Cronología: ocasiones + incidencias
        $timeline = [];
        $n = (int)round(gauss(10.5, 1.6));
        foreach (['home', 'away'] as $side) {
            if ($this->plan[$side]['tactic'] === 'pressing') {
                $n -= 2;
            }
        }
        $n = max(4, $n);
        for ($i = 0; $i < $n; $i++) {
            $timeline[] = ['m' => mt_rand(2, 90), 'k' => 'chance'];
        }
        // Eventos especiales
        foreach (['home', 'away'] as $side) {
            foreach ($this->onPitch[$side] as $pos => $p) {
                $spec = $p['specialty'];
                $type = Lineup::POSITIONS[$pos][0];
                $prob = match (true) {
                    $spec === 'quick' && in_array($type, ['W', 'FW'], true) => 5,
                    $spec === 'powerful' && $type === 'FW' => 4,
                    $spec === 'technical' && in_array($type, ['FW', 'IM'], true) => 4,
                    $spec === 'head' && in_array($type, ['CD', 'FW'], true) => 4,
                    $spec === 'unpredictable' && $type !== 'GK' => 5,
                    default => 0,
                };
                if ($prob && mt_rand(1, 100) <= $prob) {
                    $timeline[] = ['m' => mt_rand(5, 88), 'k' => 'special', 'side' => $side, 'pid' => (int)$p['id']];
                }
            }
            // Tarjetas
            $yellows = $this->poisson($this->plan[$side]['tactic'] === 'pressing' ? 1.5 : 1.0);
            for ($i = 0; $i < $yellows; $i++) {
                $timeline[] = ['m' => mt_rand(5, 90), 'k' => 'yellow', 'side' => $side];
            }
            if (mt_rand(1, 1000) <= 30) {
                $timeline[] = ['m' => mt_rand(10, 89), 'k' => 'red', 'side' => $side];
            }
            // Lesiones
            $physio = 1 - min(0.5, 0.05 * (int)$this->team[$side]['physios']);
            foreach ($this->onPitch[$side] as $p) {
                if (rnd() < 0.014 * $physio) {
                    $timeline[] = ['m' => mt_rand(3, 89), 'k' => 'injury', 'side' => $side, 'pid' => (int)$p['id']];
                }
            }
        }
        $timeline[] = ['m' => 45, 'k' => 'half'];
        usort($timeline, fn($a, $b) => $a['m'] <=> $b['m'] ?: ($a['k'] === 'half' ? 1 : 0));

        foreach ($timeline as $ev) {
            $m = $ev['m'];
            switch ($ev['k']) {
                case 'half':
                    $this->event(45, 'half', null, tx('Descanso. {home} {hg} - {ag} {away}.', ['home' => $hn, 'hg' => $this->score['home'], 'ag' => $this->score['away'], 'away' => $an]));
                    break;
                case 'chance':
                    $this->sampleRatings($m);
                    $rh = $this->ratings('home', $m);
                    $ra = $this->ratings('away', $m);
                    $ph = pow($rh['mid'], 1.4) / (pow($rh['mid'], 1.4) + pow($ra['mid'], 1.4));
                    $side = rnd() < $ph ? 'home' : 'away';
                    $this->chance($side, $m);
                    break;
                case 'special':
                    $this->specialEvent($ev['side'], $ev['pid'], $m);
                    break;
                case 'yellow':
                    $this->card($ev['side'], $m, false);
                    break;
                case 'red':
                    $this->card($ev['side'], $m, true);
                    break;
                case 'injury':
                    $this->injury($ev['side'], $ev['pid'], $m);
                    break;
            }
        }
        $this->sampleRatings(90);
        $this->event(90, 'end', null, tx('¡Final del partido! {home} {hg} - {ag} {away}.', ['home' => $hn, 'hg' => $this->score['home'], 'ag' => $this->score['away'], 'away' => $an]));
        $knockout = ($this->match['type'] ?? '') === 'cup'
            || (($this->match['type'] ?? '') === 'worldcup' && in_array($this->match['cup_stage'] ?? '', ['R16', 'QF', 'SF', '3P', 'F'], true));
        if ($knockout && $this->score['home'] === $this->score['away']) {
            $this->penalties();
        }
    }

    /** Tanda de penaltis (sólo en eliminatorias del Mundial) */
    private function penalties(): void
    {
        $this->event(90, 'info', null, tx('Empate al final del tiempo reglamentario. ¡El partido se decide en la tanda de penaltis!'));
        $pens = ['home' => 0, 'away' => 0];
        $takers = [];
        foreach (['home', 'away'] as $side) {
            $list = array_values(array_filter($this->onPitch[$side], fn($p) => true));
            usort($list, fn($a, $b) => ((float)$b['scoring'] + (float)$b['set_pieces']) <=> ((float)$a['scoring'] + (float)$a['set_pieces']));
            $takers[$side] = $list ?: [null];
        }
        $kick = function (string $side, int $i) use (&$pens, $takers) {
            $opp = $this->opp($side);
            $list = $takers[$side];
            $p = $list[$i % count($list)];
            $keeper = $this->onPitch[$opp]['GK'] ?? null;
            $att = $p ? 0.5 * (float)$p['scoring'] + 0.5 * (float)$p['set_pieces'] + (float)$p['experience'] * 0.15 : 3;
            $gk = $keeper ? (float)$keeper['keeper'] : 2;
            $prob = clamp(0.76 + ($att - $gk) * 0.02, 0.55, 0.92);
            $name = $p ? player_name($p) : $this->team[$side]['name'];
            if (rnd() < $prob) {
                $pens[$side]++;
                $this->event(120, 'pen', $side, tx('⚽ {player} marca su penalti. ({h}-{a})', ['player' => $name, 'h' => $pens['home'], 'a' => $pens['away']]));
            } else {
                $this->event(120, 'penmiss', $side, tx('❌ {player} falla su penalti. ({h}-{a})', ['player' => $name, 'h' => $pens['home'], 'a' => $pens['away']]));
            }
        };
        for ($i = 0; $i < 5; $i++) {
            $kick('home', $i);
            // ¿ya está decidido?
            if ($pens['home'] > $pens['away'] + (5 - $i) || $pens['away'] > $pens['home'] + (4 - $i)) {
                break;
            }
            $kick('away', $i);
            if ($pens['home'] > $pens['away'] + (4 - $i) || $pens['away'] > $pens['home'] + (4 - $i)) {
                break;
            }
        }
        $i = 5;
        while ($pens['home'] === $pens['away'] && $i < 40) {
            $kick('home', $i);
            $kick('away', $i);
            $i++;
        }
        if ($pens['home'] === $pens['away']) {
            $pens['home']++;
        }
        $this->pens = $pens;
        $winner = $pens['home'] > $pens['away'] ? 'home' : 'away';
        $this->event(120, 'end', $winner, tx('¡{team} gana {h}-{a} en los penaltis!', ['team' => $this->team[$winner]['name'], 'h' => $pens['home'], 'a' => $pens['away']]));
    }

    private function sampleRatings(int $m): void
    {
        foreach (['home', 'away'] as $s) {
            $r = $this->ratings($s, $m);
            foreach ($r as $k => $v) {
                $this->ratingSum[$s][$k] += $v;
            }
            $this->ratingCount[$s]++;
        }
        $h = $this->ratings('home', $m)['mid'];
        $a = $this->ratings('away', $m)['mid'];
        $this->possSum += $h / ($h + $a);
        $this->possN++;
    }

    private function opp(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    private function chance(string $side, int $m, bool $isCounter = false, float $bonus = 1.0, ?string $forceZone = null): void
    {
        $opp = $this->opp($side);
        $tactic = $this->plan[$side]['tactic'];
        $zone = $forceZone ?? $this->pickZone($tactic);
        $ra = $this->ratings($side, $m);
        $rd = $this->ratings($opp, $m);
        $this->chances[$side]++;
        $teamName = $this->team[$side]['name'];

        if ($zone === 'sp') {
            $taker = $this->findOnPitch($side, (int)$this->plan[$side]['setpieces']) ?? $this->randomPlayer($side, ['FW', 'IM']);
            $keeper = $this->onPitch[$opp]['GK'] ?? null;
            $att = 0.6 * $ra['catt'] + 0.35 * ($taker ? $this->effSkill($taker, 'set_pieces', $m) : 3);
            $def = 0.6 * $rd['cdef'] + 0.3 * ($keeper ? $this->effSkill($keeper, 'keeper', $m) : 1);
            $p = 0.75 * $this->prob($att * $bonus, $def);
            $scorer = mt_rand(1, 100) <= 45 && $taker ? $taker : $this->pickScorer($side, 'c', true);
            $intro = mt_rand(0, 1) ? tx('Falta peligrosa a favor de {team}.', ['team' => $teamName]) : tx('Saque de esquina para {team}.', ['team' => $teamName]);
        } elseif ($zone === 'ls') {
            $shooter = $this->pickScorer($side, 'ls');
            $keeper = $this->onPitch[$opp]['GK'] ?? null;
            $att = $shooter ? 0.6 * $this->effSkill($shooter, 'scoring', $m) + 0.4 * $this->effSkill($shooter, 'set_pieces', $m) : 2;
            $def = ($keeper ? $this->effSkill($keeper, 'keeper', $m) * 0.9 : 1) + 2;
            $p = 0.6 * $this->prob($att * $bonus, $def);
            $scorer = $shooter;
            $intro = tx('{player} prueba suerte con un disparo lejano.', ['player' => $shooter ? player_name($shooter) : $teamName]);
        } else {
            $attKey = ['l' => 'latt', 'c' => 'catt', 'r' => 'ratt'][$zone];
            $defKey = ['l' => 'rdef', 'c' => 'cdef', 'r' => 'ldef'][$zone];
            $scorer = $this->pickScorer($side, $zone);
            $where = ['l' => tx('por la banda izquierda'), 'c' => tx('por el centro'), 'r' => tx('por la banda derecha')][$zone];
            $intro = $isCounter ? tx('¡Contraataque de {team}!', ['team' => $teamName]) : tx('{team} se acerca con peligro {where}.', ['team' => $teamName, 'where' => $where]);
            // Lateral: el mejor regateador de esa banda desborda y pone el centro
            if ($zone !== 'c') {
                $wg = $this->bestWinger($side, strtoupper($zone), $m);
                if ($wg) {
                    $bonus *= clamp(1 + ($wg[1] - 6) * 0.03, 0.9, 1.3);
                    if ($wg[1] >= 7 && !$isCounter && (!$scorer || (int)$scorer['id'] !== (int)$wg[0]['id'])) {
                        $intro = tx('{player} desborda {where} y pone un centro peligroso al área.', ['player' => player_name($wg[0]), 'where' => $where]);
                    }
                }
            }
            $p = 0.88 * $this->prob($ra[$attKey] * $bonus, $rd[$defKey]);
        }

        $scorerName = $scorer ? player_name($scorer) : $teamName;
        if (rnd() < $p) {
            $this->score[$side]++;
            if ($scorer) {
                $this->goalsBy[(int)$scorer['id']] = ($this->goalsBy[(int)$scorer['id']] ?? 0) + 1;
            }
            $args = ['player' => $scorerName];
            $finish = [
                tx('¡GOOOL! {player} la manda al fondo de la red.', $args),
                tx('¡Gol de {player}! Definición perfecta ante el portero.', $args),
                tx('¡{player} no perdona y marca!', $args),
                tx('¡Golazo de {player}!', $args),
            ][mt_rand(0, 3)];
            $this->event($m, 'goal', $side, tx_join($intro, $finish), $scorer ? (int)$scorer['id'] : null);
        } else {
            $args = ['player' => $scorerName];
            $miss = [
                tx('{player} remata, pero el portero rival despeja.', $args),
                tx('El disparo de {player} se marcha por encima del larguero.', $args),
                tx('La defensa rival corta la jugada a tiempo.'),
                tx('{player} estrella el balón en el poste.', $args),
                tx('{player} no consigue rematar con claridad.', $args),
            ][mt_rand(0, 4)];
            $this->event($m, 'miss', $side, tx_join($intro, $miss));
            // Contraataque del rival
            if (!$isCounter && $this->plan[$opp]['tactic'] === 'counter') {
                $defAvg = ($rd['rdef'] + $rd['cdef'] + $rd['ldef']) / 3;
                $attAvg = ($ra['ratt'] + $ra['catt'] + $ra['latt']) / 3;
                if (rnd() < clamp(0.25 * $defAvg / max(1, $attAvg), 0.05, 0.55)) {
                    $this->chance($opp, min(90, $m + 1), true, 0.9, ['l', 'c', 'r'][mt_rand(0, 2)]);
                }
            }
        }
    }

    /** Jugador con más «Lateral» en una banda (extremo, lateral o delantero de esa banda) */
    private function bestWinger(string $side, string $band, int $m): ?array
    {
        $best = null;
        foreach ($this->onPitch[$side] as $pos => $p) {
            [$type, $s] = Lineup::POSITIONS[$pos];
            if ($s !== $band || !in_array($type, ['W', 'WB', 'FW', 'IM'], true)) {
                continue;
            }
            $v = $this->effSkill($p, 'winger', $m) * ($type === 'W' ? 1.0 : 0.85);
            if (!$best || $v > $best[1]) {
                $best = [$p, $v];
            }
        }
        return $best;
    }

    private function prob(float $att, float $def): float
    {
        $a = pow(max(0.1, $att), 2.6);
        $d = pow(max(0.1, $def), 2.6);
        return $a / ($a + $d);
    }

    private function pickZone(string $tactic): string
    {
        $w = match ($tactic) {
            'aim' => ['l' => 15, 'c' => 55, 'r' => 15, 'sp' => 15],
            'aow' => ['l' => 35, 'c' => 15, 'r' => 35, 'sp' => 15],
            'longshots' => ['l' => 20, 'c' => 25, 'r' => 20, 'sp' => 12, 'ls' => 23],
            default => ['l' => 25, 'c' => 35, 'r' => 25, 'sp' => 13, 'ls' => 2],
        };
        $r = mt_rand(1, array_sum($w));
        foreach ($w as $k => $v) {
            $r -= $v;
            if ($r <= 0) {
                return $k;
            }
        }
        return 'c';
    }

    private function pickScorer(string $side, string $zone, bool $setPiece = false): ?array
    {
        $weights = [];
        foreach ($this->onPitch[$side] as $pos => $p) {
            [$type, $s] = Lineup::POSITIONS[$pos];
            $base = match ($type) { 'FW' => 6, 'W' => 3, 'IM' => 2, 'WB' => 1, 'CD' => $setPiece ? 3 : 0.5, default => 0 };
            if ($zone === 'l' || $zone === 'r') {
                $match = strtoupper($zone);
                if ($s === $match) {
                    $base *= 2.2;
                }
            } elseif ($zone === 'c' && $s === 'C') {
                $base *= 1.5;
            }
            if ($zone === 'ls') {
                $base = $type === 'GK' ? 0 : 1 + (float)$p['scoring'] / 3;
            }
            if ($setPiece && $p['specialty'] === 'head') {
                $base *= 2;
            }
            $w = $base * (1 + (float)$p['scoring'] / 6);
            if ($w > 0) {
                $weights[] = [$w, $p];
            }
        }
        if (!$weights) {
            return null;
        }
        $total = array_sum(array_column($weights, 0));
        $r = rnd(0, $total);
        foreach ($weights as [$w, $p]) {
            $r -= $w;
            if ($r <= 0) {
                return $p;
            }
        }
        return $weights[0][1];
    }

    private function randomPlayer(string $side, array $types = []): ?array
    {
        $list = [];
        foreach ($this->onPitch[$side] as $pos => $p) {
            if (!$types || in_array(Lineup::POSITIONS[$pos][0], $types, true)) {
                $list[] = $p;
            }
        }
        return $list ? $list[array_rand($list)] : null;
    }

    private function specialEvent(string $side, int $pid, int $m): void
    {
        $p = $this->findOnPitch($side, $pid);
        if (!$p) {
            return;
        }
        $name = player_name($p);
        switch ($p['specialty']) {
            case 'quick':
                $this->event($m, 'special', $side, tx('⚡ {player} deja atrás a su marcador gracias a su velocidad...', ['player' => $name]));
                $this->chance($side, $m, false, 1.3, mt_rand(0, 1) ? 'l' : 'r');
                break;
            case 'powerful':
                $this->event($m, 'special', $side, tx('💪 {player} se impone físicamente en el área...', ['player' => $name]));
                $this->chance($side, $m, false, 1.3, 'c');
                break;
            case 'technical':
                $this->event($m, 'special', $side, tx('🎯 Una genialidad técnica de {player} abre la defensa...', ['player' => $name]));
                $this->chance($side, $m, false, 1.25, 'c');
                break;
            case 'head':
                $this->event($m, 'special', $side, tx('🗣 Centro al área buscando la cabeza de {player}...', ['player' => $name]));
                $this->chance($side, $m, false, 1.25, 'sp');
                break;
            case 'unpredictable':
                if (mt_rand(1, 100) <= 70) {
                    $this->event($m, 'special', $side, tx('🎲 Jugada imprevisible de {player} que sorprende a todos...', ['player' => $name]));
                    $this->chance($side, $m, false, 1.2, ['l', 'c', 'r'][mt_rand(0, 2)]);
                } else {
                    $this->event($m, 'special', $side, tx('🎲 {player} intenta algo imprevisible y pierde el balón en zona peligrosa...', ['player' => $name]));
                    $this->chance($this->opp($side), $m, false, 1.1, 'c');
                }
                break;
        }
    }

    private function card(string $side, int $m, bool $straightRed): void
    {
        $cands = [];
        foreach ($this->onPitch[$side] as $pos => $p) {
            $type = Lineup::POSITIONS[$pos][0];
            $w = match ($type) { 'CD' => 4, 'WB' => 3, 'IM' => 3, 'W' => 2, 'FW' => 1.5, default => 0.3 };
            if ($p['specialty'] === 'powerful') {
                $w *= 1.4;
            }
            $cands[] = [$w, $pos, $p];
        }
        if (!$cands) {
            return;
        }
        $r = rnd(0, array_sum(array_column($cands, 0)));
        $sel = $cands[0];
        foreach ($cands as $c) {
            $r -= $c[0];
            if ($r <= 0) {
                $sel = $c;
                break;
            }
        }
        [, $pos, $p] = $sel;
        $pid = (int)$p['id'];
        $name = player_name($p);
        if (!$straightRed) {
            $this->yellows[$pid] = ($this->yellows[$pid] ?? 0) + 1;
            if ($this->yellows[$pid] < 2) {
                $this->event($m, 'yellow', $side, tx('🟨 Tarjeta amarilla para {player} por una entrada dura.', ['player' => $name]), $pid);
                return;
            }
            $this->event($m, 'red', $side, tx('🟥 Segunda amarilla para {player}. ¡Expulsado!', ['player' => $name]), $pid);
        } else {
            $this->event($m, 'red', $side, tx('🟥 ¡Roja directa para {player} tras una falta muy violenta!', ['player' => $name]), $pid);
        }
        $this->reds[$pid] = 1;
        $this->removeFromPitch($side, $pos, $m);
    }

    private function injury(string $side, int $pid, int $m): void
    {
        $pos = null;
        foreach ($this->onPitch[$side] as $ps => $p) {
            if ((int)$p['id'] === $pid) {
                $pos = $ps;
            }
        }
        if ($pos === null) {
            return;
        }
        $p = $this->onPitch[$side][$pos];
        $age = (int)$p['age_years'];
        $weeks = mt_rand(1, 100) <= 35 ? 0.5 : round(rnd(1, 3) + max(0, $age - 28) * 0.15 + (mt_rand(1, 100) <= 15 ? rnd(2, 5) : 0), 1);
        if ($p['specialty'] === 'resilient') {
            $weeks = max(0.5, round($weeks * 0.6, 1));
        }
        $this->injuries[$pid] = $weeks;
        $txt = $weeks <= 0.5
            ? tx('🩹 {player} sufre un golpe y necesita atención.', ['player' => player_name($p)])
            : tx('🩹 {player} se lesiona (aprox. {weeks} semana(s) de baja).', ['player' => player_name($p), 'weeks' => ceil($weeks)]);
        $this->event($m, 'injury', $side, $txt, $pid);
        if ($weeks <= 0.5) {
            return; // sigue jugando
        }
        $this->removeFromPitch($side, $pos, $m);
        // Sustitución
        $type = Lineup::POSITIONS[$pos][0];
        $slot = 'SUB_' . $type;
        if (!isset($this->bench[$side][$slot])) {
            $slot = $this->bench[$side] ? array_key_first($this->bench[$side]) : null;
        }
        if ($slot !== null) {
            $sub = $this->bench[$side][$slot];
            unset($this->bench[$side][$slot]);
            $this->onPitch[$side][$pos] = $sub;
            $this->entered[$side][(int)$sub['id']] = $m;
            $this->posOf[(int)$sub['id']] = $pos;
            $this->version[$side]++;
            $this->event($m, 'sub', $side, tx('🔁 {in} entra en sustitución de {out}.', ['in' => player_name($sub), 'out' => player_name($p)]), (int)$sub['id']);
        }
    }

    private function removeFromPitch(string $side, string $pos, int $m): void
    {
        $p = $this->onPitch[$side][$pos];
        $this->left[$side][(int)$p['id']] = $m;
        unset($this->onPitch[$side][$pos]);
        $this->version[$side]++;
    }

    private function poisson(float $lambda): int
    {
        $l = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= rnd();
        } while ($p > $l);
        return $k - 1;
    }

    private function event(int $m, string $type, ?string $side, string $text, ?int $pid = null): void
    {
        $this->events[] = [
            'm' => $m, 't' => $type, 's' => $side, 'x' => $text, 'p' => $pid,
            'sc' => $this->score['home'] . '-' . $this->score['away'],
        ];
    }

    /* ------------------------------------------------------------------
     *  Guardado de resultados
     * ------------------------------------------------------------------ */

    private function persist(): array
    {
        $match = $this->match;
        $isLeague = $match['type'] === 'league';
        $isCup = $match['type'] === 'worldcup';
        $ts = now();
        $season = (int)$match['season'];
        $week = (int)$match['week'];

        // Valoraciones medias
        $ratings = [];
        foreach (['home', 'away'] as $s) {
            $n = max(1, $this->ratingCount[$s]);
            $ratings[$s] = array_map(fn($v) => round($v / $n, 2), $this->ratingSum[$s]);
            $ratings[$s]['tactic'] = $this->plan[$s]['tactic'];
            $ratings[$s]['attitude'] = $this->plan[$s]['attitude'];
            $ratings[$s]['chances'] = $this->chances[$s];
        }
        $poss = (int)round(($this->possN ? $this->possSum / $this->possN : 0.5) * 100);

        // Asistencia e ingresos
        $home = $this->team['home'];
        if ($isCup) {
            // Partidos del Mundial en campo neutral: sin taquilla para nadie
            $attendance = mt_rand(38000, 82000);
            $income = 0;
        } else {
            [$attendance, $demand, $capacity, $weather] = $this->crowd($home, $this->team['away']);
            $income = $this->ticketIncome($home, $attendance);
            if ($match['type'] === 'friendly') {
                $income = (int)round($income * 0.5);
            }
        }

        // La crónica cuenta cuántos aficionados entraron al estadio
        if ($isCup) {
            $crowdText = tx('🏟 {n} espectadores llenan las gradas del estadio neutral para este partido del Mundial.', ['n' => num($attendance)]);
        } elseif ($attendance >= $capacity && $capacity > 0) {
            $crowdArgs = ['weather' => $weather, 'stadium' => $home['stadium_name'], 'n' => num($attendance)];
            $crowdText = $demand > $capacity
                ? tx('🏟 {weather}. ¡Lleno absoluto en el {stadium}! {n} aficionados (100% del aforo) y unos {left} se quedaron sin entrada.', $crowdArgs + ['left' => num($demand - $capacity)])
                : tx('🏟 {weather}. ¡Lleno absoluto en el {stadium}! {n} aficionados (100% del aforo).', $crowdArgs);
        } else {
            $crowdText = tx('🏟 {weather}. {n} aficionados en el {stadium} ({pct}% del aforo de {cap}).', [
                'weather' => $weather, 'n' => num($attendance), 'stadium' => $home['stadium_name'],
                'pct' => $capacity ? (int)round($attendance / $capacity * 100) : 0, 'cap' => num($capacity),
            ]);
        }
        array_splice($this->events, 1, 0, [['m' => 0, 't' => 'crowd', 's' => null, 'x' => $crowdText, 'p' => null, 'sc' => '0-0']]);

        DB::update('matches', [
            'status'          => 'played',
            'home_goals'      => $this->score['home'],
            'away_goals'      => $this->score['away'],
            'attendance'      => $attendance,
            'home_possession' => $poss,
            'home_tactic'     => $this->plan['home']['tactic'],
            'away_tactic'     => $this->plan['away']['tactic'],
            'ratings'         => json_encode($ratings, JSON_UNESCAPED_UNICODE),
            'report'          => json_encode($this->events, JSON_UNESCAPED_UNICODE),
            'played_at'       => $ts,
            'home_pens'       => $this->pens['home'] ?? null,
            'away_pens'       => $this->pens['away'] ?? null,
        ], 'id = ?', [$match['id']]);
        DB::exec('DELETE FROM match_lineups WHERE match_id = ?', [$match['id']]);

        if (!$isCup) {
            $this->stadiumNotice($home, $attendance, $demand, $capacity);
        }
        if ($income > 0) {
            Economy::transaction((int)$home['id'], $income, 'tickets', tx('Entradas: {n} espectadores', ['n' => num($attendance)]), $season, $week);
        }

        // Sanciones: los que estaban sancionados cumplen un partido de liga
        if ($isLeague) {
            foreach (['home', 'away'] as $s) {
                DB::exec('UPDATE players SET suspended = suspended - 1 WHERE team_id = ? AND suspended > 0', [$this->team[$s]['id']]);
            }
        }

        // Jugadores
        foreach (['home', 'away'] as $s) {
            $teamId = (int)$this->team[$s]['id'];
            $all = [];
            foreach ($this->plan[$s]['starters'] as $p) {
                $all[(int)$p['id']] = $p;
            }
            foreach ($this->entered[$s] as $pid => $_) {
                if (!isset($all[$pid])) {
                    foreach ($this->plan[$s]['bench'] as $bp) {
                        if ((int)$bp['id'] === $pid) {
                            $all[$pid] = $bp;
                        }
                    }
                }
            }
            foreach ($all as $pid => $p) {
                $in = $this->entered[$s][$pid] ?? 0;
                $out = $this->left[$s][$pid] ?? 90;
                $minutes = max(1, $out - $in);
                $pos = $this->posOf[$pid];
                $goals = $this->goalsBy[$pid] ?? 0;
                $yellow = min(2, $this->yellows[$pid] ?? 0);
                $red = $this->reds[$pid] ?? 0;
                $inj = isset($this->injuries[$pid]) && $this->injuries[$pid] > 0.5 ? 1 : 0;
                $stars = $this->stars($p, $pos, $goals);
                DB::insert('match_players', [
                    'match_id' => $match['id'], 'player_id' => $pid, 'team_id' => $teamId, 'position' => $pos,
                    'minutes' => $minutes, 'stars' => $stars, 'goals' => $goals, 'yellow' => $yellow, 'red' => $red, 'injured' => $inj,
                ]);
                $xp = ($isCup ? 0.15 : ($isLeague ? 0.06 : ($match['type'] === 'cup' ? 0.05 : 0.012))) * $minutes / 90;
                $sets = ['experience = LEAST(20, experience + ?)', 'matches_career = matches_career + 1'];
                $params = [$xp];
                if ($isCup) {
                    $sets[] = 'caps = caps + 1';
                    if ($goals) {
                        $sets[] = 'goals_national = goals_national + ?';
                        $params[] = $goals;
                    }
                    $this->notifyClub($p, (int)$match['id'], $goals, $inj ? ($this->injuries[$pid] ?? 0) : 0);
                }
                if ($goals) {
                    $sets[] = 'goals_career = goals_career + ?';
                    $params[] = $goals;
                    if ($isLeague) {
                        $sets[] = 'goals_season = goals_season + ?';
                        $params[] = $goals;
                    }
                }
                if ($isLeague) {
                    if ($red) {
                        $sets[] = 'suspended = suspended + 1';
                    } elseif ($yellow) {
                        $sets[] = 'yellow_cards = yellow_cards + ?';
                        $params[] = $yellow;
                    }
                }
                if (isset($this->injuries[$pid])) {
                    $sets[] = 'injury_weeks = GREATEST(injury_weeks, ?)';
                    $params[] = $this->injuries[$pid];
                }
                $params[] = $pid;
                DB::exec('UPDATE players SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            }
            // Acumulación de amarillas: 2 = 1 partido de sanción
            if ($isLeague) {
                DB::exec('UPDATE players SET suspended = suspended + 1, yellow_cards = yellow_cards - 2 WHERE team_id = ? AND yellow_cards >= 2', [$teamId]);
            }
            if ($this->injuries) {
                PlayerFactory::refresh(array_keys($this->injuries));
            }
        }

        if ($isCup) {
            return ['home' => $this->score['home'], 'away' => $this->score['away']];
        }

        // Efectos en el equipo: confianza, espíritu, afición
        foreach (['home', 'away'] as $s) {
            $o = $this->opp($s);
            $t = $this->team[$s];
            $diff = $this->score[$s] - $this->score[$o];
            $conf = clamp((float)$t['confidence'] + clamp($diff * 0.35, -1.5, 1.5), 0, 10);
            $spirit = (float)$t['team_spirit'];
            if ($this->plan[$s]['attitude'] === 'pic') {
                $spirit += 0.6;
            } elseif ($this->plan[$s]['attitude'] === 'mots') {
                $spirit -= 1.0;
            }
            $spirit = clamp($spirit, 0, 10);
            $fans = (int)$t['fans'];
            $mood = (float)$t['fan_mood'];
            if ($isLeague) {
                if ($diff > 0) {
                    $fans = (int)round($fans * (1 + rnd(0.01, 0.025)));
                    $mood += 0.3;
                    SponsorService::winBonus((int)$t['id'], $season, $week);
                } elseif ($diff === 0) {
                    $fans = (int)round($fans * (1 + rnd(0, 0.006)));
                } else {
                    $fans = (int)round($fans * (1 - rnd(0.004, 0.012)));
                    $mood -= 0.3;
                }
            }
            DB::update('teams', [
                'confidence' => round($conf, 2), 'team_spirit' => round($spirit, 2),
                'fans' => max(100, $fans), 'fan_mood' => round(clamp($mood, 0, 10), 2),
            ], 'id = ?', [$t['id']]);

            if (!(int)$t['is_bot']) {
                $res = $diff > 0 ? tx('Victoria') : ($diff === 0 ? tx('Empate') : tx('Derrota'));
                $stage = (string)($match['cup_stage'] ?? '');
                $kind = match ($match['type']) {
                    'league' => tx('Liga · Jornada {n}', ['n' => (int)$match['round']]),
                    'cup'    => tx('Copa · {stage}', ['stage' => isset(CupService::STAGES[$stage]) ? tx(CupService::STAGES[$stage]) : $stage]),
                    default  => tx('Amistoso'),
                };
                DB::insert('team_events', [
                    'team_id' => $t['id'], 'created_at' => $ts, 'type' => 'match',
                    'message' => tx('{result} ({kind}): {home} {hg} - {ag} {away}', [
                        'result' => $res, 'kind' => $kind, 'home' => $home['name'], 'hg' => $this->score['home'],
                        'ag' => $this->score['away'], 'away' => $this->team['away']['name'],
                    ]),
                ]);
                foreach ($this->injuries as $pid => $w) {
                    if ($w > 0.5 && isset($this->entered[$s][$pid])) {
                        $pl = DB::row('SELECT first_name, last_name FROM players WHERE id = ?', [$pid]);
                        if ($pl) {
                            DB::insert('team_events', ['team_id' => $t['id'], 'created_at' => $ts, 'type' => 'injury',
                                'message' => tx('{player} se ha lesionado. Baja estimada: {weeks} semana(s).', ['player' => player_name($pl), 'weeks' => ceil($w)])]);
                        }
                    }
                }
            }
        }
        return ['home' => $this->score['home'], 'away' => $this->score['away']];
    }

    /** El club del jugador recibe una prima y, si es de un mánager, una noticia */
    private function notifyClub(array $p, int $matchId, int $goals, float $injury): void
    {
        $club = DB::row('SELECT t.id, t.is_bot FROM players p JOIN teams t ON t.id = p.team_id WHERE p.id = ?', [$p['id']]);
        if (!$club) {
            return;
        }
        $bonus = Config::int('worldcup', 'club_bonus', 5000);
        if ($bonus > 0) {
            Economy::transaction((int)$club['id'], $bonus, 'prizes', tx('Prima por internacional: {player}', ['player' => player_name($p)]), (int)$this->match['season'], (int)$this->match['week']);
        }
        if (!(int)$club['is_bot']) {
            $country = $this->team['home']['country_id'] == $p['country_id'] ? $this->team['home']['name'] : $this->team['away']['name'];
            $msg = $goals
                ? tx('🌍 {player} ha jugado el Mundial con {country} y ha marcado {goals} gol(es).', ['player' => player_name($p), 'country' => $country, 'goals' => $goals])
                : tx('🌍 {player} ha jugado el Mundial con {country}.', ['player' => player_name($p), 'country' => $country]);
            if ($injury > 0.5) {
                $msg = tx_join($msg, tx('Por desgracia se ha lesionado ({weeks} semana(s)).', ['weeks' => ceil($injury)]));
            }
            DB::insert('team_events', ['team_id' => $club['id'], 'created_at' => now(), 'type' => 'national', 'message' => $msg]);
        }
    }

    private function stars(array $p, string $pos, int $goals): float
    {
        $type = Lineup::POSITIONS[$pos][0];
        $m = 60;
        $main = match ($type) {
            'GK' => $this->effSkill($p, 'keeper', $m),
            'CD' => $this->effSkill($p, 'defending', $m),
            'WB' => $this->effSkill($p, 'defending', $m) * 0.7 + $this->effSkill($p, 'winger', $m) * 0.3,
            'IM' => $this->effSkill($p, 'playmaking', $m) * 0.8 + $this->effSkill($p, 'passing', $m) * 0.2,
            'W'  => $this->effSkill($p, 'winger', $m) * 0.7 + $this->effSkill($p, 'playmaking', $m) * 0.3,
            'FW' => $this->effSkill($p, 'scoring', $m) * 0.8 + $this->effSkill($p, 'passing', $m) * 0.2,
            default => 3,
        };
        $s = $main * 0.55 + $goals * 0.5;
        return round(clamp($s, 0.5, 15) * 2) / 2;
    }

    /**
     * Afluencia dinámica de aficionados.
     * Depende de los socios, su ánimo, la confianza en el equipo, la división, el atractivo del rival,
     * la importancia del partido (final de liga, rondas de copa) y el tiempo que haga ese día.
     * Devuelve [asistencia, demanda, aforo, texto del tiempo (tx)].
     */
    private function crowd(array $home, array $away): array
    {
        $cap = (int)$home['seats_terraces'] + (int)$home['seats_basic'] + (int)$home['seats_roof'] + (int)$home['seats_vip'];
        $level = (int)(DB::value('SELECT level FROM league_units WHERE id = ?', [$home['league_unit_id']]) ?? 1);
        $divs = Config::int('game', 'divisions', 3);
        $levelF = 1 + max(0, $divs - $level) * 0.25;
        $moodF = 0.6 + (float)$home['fan_mood'] * 0.08;
        $confF = 0.75 + (float)$home['confidence'] * 0.05;
        $rival = 0.85 + min(0.35, (int)$away['fans'] / max(1, (int)$home['fans']) * 0.15);
        $type = $this->match['type'];
        $importance = match ($type) {
            'league' => (int)$this->match['round'] >= 12 ? 1.08 : 1.0,
            'cup' => ['R1' => 0.75, 'R128' => 0.8, 'R64' => 0.8, 'R32' => 0.9, 'R16' => 1.0, 'QF' => 1.1, 'SF' => 1.25, 'F' => 1.5][$this->match['cup_stage'] ?? ''] ?? 0.9,
            default => 0.5,
        };
        $r = mt_rand(1, 100);
        [$weather, $wF] = $r <= 40 ? [tx('Día soleado'), 1.05] : ($r <= 75 ? [tx('Cielo nublado'), 1.0] : ($r <= 95 ? [tx('Tarde de lluvia'), 0.88] : [tx('Tormenta'), 0.75]));
        $demand = (int)round((int)$home['fans'] * 7.5 * $moodF * $confF * $levelF * $rival * $importance * $wF * rnd(0.95, 1.05));
        return [min($cap, max(0, $demand)), max(0, $demand), $cap, $weather];
    }

    /** Avisos por mensaje al mánager cuando el estadio se llena o se queda muy vacío */
    private function stadiumNotice(array $home, int $attendance, int $demand, int $cap): void
    {
        if ((int)$home['is_bot'] || !$home['user_id'] || !in_array($this->match['type'], ['league', 'cup'], true) || $cap <= 0) {
            return;
        }
        $last = $home['stadium_notice_at'] ?? null;
        $ts = now();
        $site = (string)Config::get('app', 'site_name', 'GoalTrick');
        if ($attendance >= $cap) {
            if ($last && strtotime((string)$last) > time() - 14 * 86400) {
                return;
            }
            $left = max(0, $demand - $cap);
            $subject = tx('🏟 ¡Estadio lleno! Piensa en ampliarlo');
            $body = tx("¡Enhorabuena! En el último partido en casa ({home} - {away}) el {stadium} colgó el cartel de «no hay billetes»: {n} espectadores, el 100% del aforo.\n\n{extra}Te recomendamos ampliar el estadio en la sección «Estadio»: más localidades significan más ingresos por taquilla en cada partido en casa.\n\n— El departamento de taquillas de {site}", [
                'home' => $this->team['home']['name'], 'away' => $this->team['away']['name'], 'stadium' => $home['stadium_name'],
                'n' => num($attendance), 'site' => $site,
                'extra' => $left > 0 ? tx("Calculamos que unos {n} aficionados más se quedaron sin entrada.\n\n", ['n' => num($left)]) : '',
            ]);
        } else {
            if ($attendance >= $cap * 0.5 || ($last && strtotime((string)$last) > time() - 28 * 86400)) {
                return;
            }
            // Sólo si los últimos 3 partidos oficiales en casa también estuvieron por debajo del 50%
            $recent = DB::all("SELECT attendance FROM matches WHERE home_team_id = ? AND type IN ('league', 'cup') AND status = 'played' ORDER BY played_at DESC LIMIT 3", [$home['id']]);
            if (count($recent) < 3) {
                return;
            }
            foreach ($recent as $r) {
                if ((int)$r['attendance'] >= $cap * 0.5) {
                    return;
                }
            }
            $avg = (int)round(array_sum(array_column($recent, 'attendance')) / 3);
            $subject = tx('🏟 Demasiadas localidades vacías');
            $body = tx("En tus últimos partidos en casa la media ha sido de {avg} espectadores para un aforo de {cap} ({pct}%).\n\nCada asiento cuesta mantenimiento todas las semanas. Si la afición no crece, quizá te convenga reducir algunas localidades (sobre todo las más caras de mantener) en la sección «Estadio», o mejorar los resultados y la confianza del equipo para atraer a más público.\n\n— El departamento de taquillas de {site}", [
                'avg' => num($avg), 'cap' => num($cap), 'pct' => (int)round($avg / $cap * 100), 'site' => $site,
            ]);
        }
        DB::insert('messages', ['from_user_id' => null, 'to_user_id' => $home['user_id'], 'subject' => $subject, 'body' => $body, 'created_at' => $ts]);
        DB::exec('UPDATE teams SET stadium_notice_at = ? WHERE id = ?', [$ts, $home['id']]);
    }

    private function ticketIncome(array $home, int $attendance): int
    {
        $types = ['terraces', 'basic', 'roof', 'vip'];
        $cap = 0;
        foreach ($types as $t) {
            $cap += (int)$home['seats_' . $t];
        }
        if ($cap <= 0) {
            return 0;
        }
        $ratio = $attendance / $cap;
        $income = 0.0;
        foreach ($types as $t) {
            $income += (int)$home['seats_' . $t] * $ratio * Config::float('economy', 'price_' . $t, 10);
        }
        return (int)round($income);
    }
}
