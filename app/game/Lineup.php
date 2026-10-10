<?php
declare(strict_types=1);

/**
 * Posiciones, alineaciones guardadas y alineación automática.
 */
final class Lineup
{
    /** clave => [tipo, lado, etiqueta] */
    public const POSITIONS = [
        'GK'  => ['GK', 'C', 'Portero'],
        'RB'  => ['WB', 'R', 'Lateral derecho'],
        'RCD' => ['CD', 'R', 'Central derecho'],
        'CD'  => ['CD', 'C', 'Central'],
        'LCD' => ['CD', 'L', 'Central izquierdo'],
        'LB'  => ['WB', 'L', 'Lateral izquierdo'],
        'RW'  => ['W',  'R', 'Extremo derecho'],
        'RIM' => ['IM', 'R', 'Medio derecho'],
        'IM'  => ['IM', 'C', 'Medio centro'],
        'LIM' => ['IM', 'L', 'Medio izquierdo'],
        'LW'  => ['W',  'L', 'Extremo izquierdo'],
        'RFW' => ['FW', 'R', 'Delantero derecho'],
        'FW'  => ['FW', 'C', 'Delantero centro'],
        'LFW' => ['FW', 'L', 'Delantero izquierdo'],
    ];

    /** Banquillo: un suplente por tipo */
    public const BENCH = [
        'SUB_GK' => 'GK', 'SUB_CD' => 'CD', 'SUB_WB' => 'WB', 'SUB_IM' => 'IM', 'SUB_W' => 'W', 'SUB_FW' => 'FW',
    ];

    public const BENCH_LABELS = [
        'SUB_GK' => 'Portero suplente', 'SUB_CD' => 'Defensa suplente', 'SUB_WB' => 'Lateral suplente',
        'SUB_IM' => 'Medio suplente', 'SUB_W' => 'Extremo suplente', 'SUB_FW' => 'Delantero suplente',
    ];

    public const ORDERS = ['normal' => 'Normal', 'offensive' => 'Ofensivo', 'defensive' => 'Defensivo'];

    public const FORMATIONS = [
        '4-4-2' => ['GK', 'RB', 'RCD', 'LCD', 'LB', 'RW', 'RIM', 'LIM', 'LW', 'RFW', 'LFW'],
        '3-5-2' => ['GK', 'RCD', 'CD', 'LCD', 'RW', 'RIM', 'IM', 'LIM', 'LW', 'RFW', 'LFW'],
        '4-5-1' => ['GK', 'RB', 'RCD', 'LCD', 'LB', 'RW', 'RIM', 'IM', 'LIM', 'LW', 'FW'],
        '5-3-2' => ['GK', 'RB', 'RCD', 'CD', 'LCD', 'LB', 'RIM', 'IM', 'LIM', 'RFW', 'LFW'],
        '4-3-3' => ['GK', 'RB', 'RCD', 'LCD', 'LB', 'RIM', 'IM', 'LIM', 'RFW', 'FW', 'LFW'],
        '2-5-3' => ['GK', 'RCD', 'LCD', 'RW', 'RIM', 'IM', 'LIM', 'LW', 'RFW', 'FW', 'LFW'],
    ];

    /**
     * Contribución de cada posición a los 7 sectores (lado derecho; el izquierdo se refleja).
     * Sectores: mid, rdef, cdef, ldef, ratt, catt, latt
     */
    public static function contributions(string $type, string $side): array
    {
        $c = match ($type) {
            'GK' => ['cdef' => ['keeper' => 0.87, 'defending' => 0.35], 'rdef' => ['keeper' => 0.61, 'defending' => 0.25], 'ldef' => ['keeper' => 0.61, 'defending' => 0.25]],
            'CD' => $side === 'C'
                ? ['cdef' => ['defending' => 1.0], 'rdef' => ['defending' => 0.26], 'ldef' => ['defending' => 0.26], 'mid' => ['playmaking' => 0.25]]
                : ['cdef' => ['defending' => 1.0], 'rdef' => ['defending' => 0.52], 'mid' => ['playmaking' => 0.25]],
            'WB' => ['rdef' => ['defending' => 0.92, 'winger' => 0.12], 'cdef' => ['defending' => 0.38], 'mid' => ['playmaking' => 0.15], 'ratt' => ['winger' => 0.62]],
            'IM' => $side === 'C'
                ? ['mid' => ['playmaking' => 1.0], 'cdef' => ['defending' => 0.40], 'rdef' => ['defending' => 0.19], 'ldef' => ['defending' => 0.19],
                   'catt' => ['passing' => 0.33], 'ratt' => ['passing' => 0.13], 'latt' => ['passing' => 0.13]]
                : ['mid' => ['playmaking' => 1.0], 'cdef' => ['defending' => 0.25], 'rdef' => ['defending' => 0.27],
                   'catt' => ['passing' => 0.23], 'ratt' => ['passing' => 0.26, 'winger' => 0.12]],
            'W'  => ['ratt' => ['winger' => 1.0, 'passing' => 0.26], 'rdef' => ['defending' => 0.35, 'winger' => 0.1], 'cdef' => ['defending' => 0.2],
                     'mid' => ['playmaking' => 0.45], 'catt' => ['passing' => 0.11]],
            'FW' => $side === 'C'
                ? ['catt' => ['scoring' => 1.0, 'passing' => 0.37], 'ratt' => ['winger' => 0.25, 'passing' => 0.15], 'latt' => ['winger' => 0.25, 'passing' => 0.15], 'mid' => ['playmaking' => 0.25]]
                : ['catt' => ['scoring' => 0.6, 'passing' => 0.3], 'ratt' => ['winger' => 0.62, 'scoring' => 0.3, 'passing' => 0.2], 'latt' => ['passing' => 0.1], 'mid' => ['playmaking' => 0.25]],
            default => [],
        };
        if ($side === 'L') {
            $m = [];
            foreach ($c as $sector => $w) {
                $sector = match ($sector) { 'rdef' => 'ldef', 'ldef' => 'rdef', 'ratt' => 'latt', 'latt' => 'ratt', default => $sector };
                $m[$sector] = $w;
            }
            $c = $m;
        }
        return $c;
    }

    /** Puntuación de un jugador para una posición (para la alineación automática) */
    public static function suitability(array $p, string $pos): float
    {
        [$type, $side] = self::POSITIONS[$pos];
        $sum = 0.0;
        foreach (self::contributions($type, $side) as $weights) {
            foreach ($weights as $skill => $w) {
                $sum += (float)$p[$skill] * $w;
            }
        }
        return $sum * (0.75 + (float)$p['form'] * 0.04);
    }

    public static function available(int $teamId): array
    {
        // Las selecciones nacionales convocan a los mejores jugadores de su nacionalidad
        $t = DB::row('SELECT is_national, country_id FROM teams WHERE id = ?', [$teamId]);
        if ($t && (int)$t['is_national']) {
            return NationService::squad((int)$t['country_id'], true);
        }
        return DB::all('SELECT * FROM players WHERE team_id = ? AND retired = 0 AND injury_weeks <= 0 AND suspended = 0', [$teamId]);
    }

    public static function get(int $teamId): array
    {
        $row = DB::row('SELECT * FROM team_lineups WHERE team_id = ?', [$teamId]);
        if (!$row) {
            return ['positions' => [], 'orders' => [], 'tactic' => 'normal', 'attitude' => 'normal', 'captain_id' => null, 'setpieces_id' => null, 'for_match_id' => null];
        }
        return self::decode($row) + ['for_match_id' => isset($row['for_match_id']) && $row['for_match_id'] ? (int)$row['for_match_id'] : null];
    }

    private static function decode(array $row): array
    {
        return [
            'positions'    => json_decode($row['positions'], true) ?: [],
            'orders'       => json_decode((string)$row['orders'], true) ?: [],
            'tactic'       => $row['tactic'],
            'attitude'     => $row['attitude'],
            'captain_id'   => $row['captain_id'] ? (int)$row['captain_id'] : null,
            'setpieces_id' => $row['setpieces_id'] ? (int)$row['setpieces_id'] : null,
        ];
    }

    /**
     * Guarda la alineación enviada. $forMatchId = partido para el que se envía (si el mánager no envía
     * alineación para un partido se usa su alineación predeterminada, ver forMatch()).
     */
    public static function save(int $teamId, array $lineup, ?int $forMatchId = null): void
    {
        DB::exec(
            'INSERT INTO team_lineups (team_id, positions, orders, tactic, attitude, captain_id, setpieces_id, for_match_id, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE positions = VALUES(positions), orders = VALUES(orders), tactic = VALUES(tactic),
               attitude = VALUES(attitude), captain_id = VALUES(captain_id), setpieces_id = VALUES(setpieces_id),
               for_match_id = VALUES(for_match_id), updated_at = VALUES(updated_at)',
            [
                $teamId,
                json_encode($lineup['positions']),
                json_encode($lineup['orders'] ?? []),
                $lineup['tactic'] ?? 'normal',
                $lineup['attitude'] ?? 'normal',
                $lineup['captain_id'] ?? null,
                $lineup['setpieces_id'] ?? null,
                $forMatchId,
                now(),
            ]
        );
    }

    /* ---------------- Alineaciones guardadas (0 = predeterminada, 1..3 = libres) ---------------- */

    public const PRESET_SLOTS = 3;

    /** slot => ['name' => ..., 'lineup' => [...], 'updated_at' => ...] */
    public static function presets(int $teamId): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM lineup_presets WHERE team_id = ? ORDER BY slot', [$teamId]) as $r) {
            $out[(int)$r['slot']] = ['name' => $r['name'], 'lineup' => self::decode($r), 'updated_at' => $r['updated_at']];
        }
        return $out;
    }

    public static function savePreset(int $teamId, int $slot, string $name, array $lineup): void
    {
        $slot = max(0, min(self::PRESET_SLOTS, $slot));
        DB::exec(
            'INSERT INTO lineup_presets (team_id, slot, name, positions, orders, tactic, attitude, captain_id, setpieces_id, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), positions = VALUES(positions), orders = VALUES(orders), tactic = VALUES(tactic),
               attitude = VALUES(attitude), captain_id = VALUES(captain_id), setpieces_id = VALUES(setpieces_id), updated_at = VALUES(updated_at)',
            [
                $teamId, $slot, mb_substr($name, 0, 40),
                json_encode($lineup['positions']), json_encode($lineup['orders'] ?? []),
                $lineup['tactic'] ?? 'normal', $lineup['attitude'] ?? 'normal',
                $lineup['captain_id'] ?? null, $lineup['setpieces_id'] ?? null, now(),
            ]
        );
    }

    public static function deletePreset(int $teamId, int $slot): void
    {
        DB::exec('DELETE FROM lineup_presets WHERE team_id = ? AND slot = ?', [$teamId, $slot]);
    }

    /** Alineación que se usará en un partido concreto: la enviada para él o, si no, la predeterminada */
    public static function planned(int $teamId, int $matchId = 0): array
    {
        $sent = self::get($teamId);
        if ($matchId <= 0 || ($sent['for_match_id'] ?? null) === $matchId) {
            return $sent;
        }
        $default = self::presets($teamId)[0] ?? null;
        if ($default && $default['lineup']['positions']) {
            return $default['lineup'] + ['for_match_id' => null, 'is_default' => true];
        }
        return $sent;
    }

    /** Genera la mejor alineación posible para una formación con los jugadores disponibles */
    public static function auto(int $teamId, string $formation = '4-4-2', ?array $players = null): array
    {
        $players = $players ?? self::available($teamId);
        $byId = [];
        foreach ($players as $p) {
            $byId[(int)$p['id']] = $p;
        }
        $slots = self::FORMATIONS[$formation] ?? self::FORMATIONS['4-4-2'];
        $positions = [];

        // Portero primero: el que tenga más portería
        usort($players, fn($a, $b) => $b['keeper'] <=> $a['keeper']);
        if ($players) {
            $positions['GK'] = (int)$players[0]['id'];
            unset($byId[(int)$players[0]['id']]);
        }
        // Resto: asignación voraz por la mejor relación jugador/posición
        $remaining = array_values(array_filter($slots, fn($s) => $s !== 'GK'));
        while ($remaining && $byId) {
            $best = null;
            foreach ($remaining as $pos) {
                foreach ($byId as $id => $p) {
                    $score = self::suitability($p, $pos);
                    // pequeña preferencia por su posición natural
                    if ($p['role'] === self::POSITIONS[$pos][0]) {
                        $score *= 1.08;
                    }
                    if ($best === null || $score > $best[0]) {
                        $best = [$score, $pos, $id];
                    }
                }
            }
            [, $pos, $id] = $best;
            $positions[$pos] = $id;
            unset($byId[$id]);
            $remaining = array_values(array_diff($remaining, [$pos]));
        }
        // Banquillo
        foreach (self::BENCH as $slot => $type) {
            $bestId = null;
            $bestScore = -1;
            foreach ($byId as $id => $p) {
                $pos = match ($type) { 'GK' => 'GK', 'CD' => 'CD', 'WB' => 'RB', 'IM' => 'IM', 'W' => 'RW', default => 'FW' };
                $s = $type === 'GK' ? (float)$p['keeper'] : self::suitability($p, $pos);
                if ($s > $bestScore) {
                    $bestScore = $s;
                    $bestId = $id;
                }
            }
            if ($bestId !== null) {
                $positions[$slot] = $bestId;
                unset($byId[$bestId]);
            }
        }
        // Capitán: el de más liderazgo+experiencia entre los titulares. Lanzador: más balón parado.
        $captain = null;
        $setp = null;
        foreach ($positions as $slot => $id) {
            if (str_starts_with($slot, 'SUB_')) {
                continue;
            }
            $p = self::findIn($players, $id);
            if (!$p) {
                continue;
            }
            if (!$captain || ($p['leadership'] + $p['experience'] / 3) > ($captain['leadership'] + $captain['experience'] / 3)) {
                $captain = $p;
            }
            if ($slot !== 'GK' && (!$setp || $p['set_pieces'] > $setp['set_pieces'])) {
                $setp = $p;
            }
        }
        return [
            'positions'    => $positions,
            'orders'       => [],
            'tactic'       => 'normal',
            'attitude'     => 'normal',
            'captain_id'   => $captain ? (int)$captain['id'] : null,
            'setpieces_id' => $setp ? (int)$setp['id'] : null,
        ];
    }

    private static function findIn(array $players, int $id): ?array
    {
        foreach ($players as $p) {
            if ((int)$p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Devuelve la alineación definitiva para un partido: valida la guardada y
     * rellena huecos (lesionados, sancionados o vendidos) con suplentes.
     * Resultado: ['starters' => [pos => player], 'bench' => [slot => player], tactic, attitude, captain, setpieces, orders]
     */
    public static function forMatch(array $team, int $matchId = 0): array
    {
        $teamId = (int)$team['id'];
        $players = self::available($teamId);
        $byId = [];
        foreach ($players as $p) {
            $byId[(int)$p['id']] = $p;
        }
        $lineup = (int)$team['is_bot'] ? self::auto($teamId, self::botFormation($teamId), $players) : self::planned($teamId, $matchId);
        if (!$lineup['positions']) {
            $lineup = self::auto($teamId, '4-4-2', $players);
        }

        $starters = [];
        $bench = [];
        $used = [];
        foreach ($lineup['positions'] as $slot => $pid) {
            $pid = (int)$pid;
            if (!isset($byId[$pid]) || isset($used[$pid])) {
                continue;
            }
            if (isset(self::POSITIONS[$slot])) {
                $starters[$slot] = $byId[$pid];
                $used[$pid] = true;
            } elseif (isset(self::BENCH[$slot])) {
                $bench[$slot] = $byId[$pid];
                $used[$pid] = true;
            }
        }
        // Huecos en la alineación titular original
        foreach ($lineup['positions'] as $slot => $pid) {
            if (!isset(self::POSITIONS[$slot]) || isset($starters[$slot])) {
                continue;
            }
            $type = self::POSITIONS[$slot][0];
            $benchSlot = 'SUB_' . $type;
            if (isset($bench[$benchSlot])) {
                $starters[$slot] = $bench[$benchSlot];
                unset($bench[$benchSlot]);
                continue;
            }
            // Cualquier jugador disponible no utilizado, el más adecuado
            $best = null;
            foreach ($byId as $id => $p) {
                if (isset($used[$id])) {
                    continue;
                }
                $s = self::suitability($p, $slot);
                if (!$best || $s > $best[0]) {
                    $best = [$s, $p];
                }
            }
            if ($best) {
                $starters[$slot] = $best[1];
                $used[(int)$best[1]['id']] = true;
            } elseif ($bench) {
                $slotB = array_key_first($bench);
                $starters[$slot] = $bench[$slotB];
                unset($bench[$slotB]);
            }
        }
        // Sin portero: el que más portería tenga pasa a la portería
        if (!isset($starters['GK']) && $starters) {
            $bestPos = null;
            foreach ($starters as $pos => $p) {
                if ($bestPos === null || $p['keeper'] > $starters[$bestPos]['keeper']) {
                    $bestPos = $pos;
                }
            }
            $starters['GK'] = $starters[$bestPos];
            unset($starters[$bestPos]);
        }
        $ids = array_map(fn($p) => (int)$p['id'], $starters);
        $captain = in_array((int)$lineup['captain_id'], $ids, true) ? (int)$lineup['captain_id'] : ($ids ? $ids[array_key_first($ids)] : null);
        $setp = in_array((int)$lineup['setpieces_id'], $ids, true) ? (int)$lineup['setpieces_id'] : null;
        if (!$setp) {
            $bestSp = null;
            foreach ($starters as $pos => $p) {
                if ($pos !== 'GK' && (!$bestSp || $p['set_pieces'] > $bestSp['set_pieces'])) {
                    $bestSp = $p;
                }
            }
            $setp = $bestSp ? (int)$bestSp['id'] : null;
        }
        return [
            'starters'  => $starters,
            'bench'     => $bench,
            'orders'    => $lineup['orders'] ?? [],
            'tactic'    => (int)$team['is_bot'] ? self::botTactic() : ($lineup['tactic'] ?? 'normal'),
            'attitude'  => (int)$team['is_bot'] ? 'normal' : ($lineup['attitude'] ?? 'normal'),
            'captain'   => $captain,
            'setpieces' => $setp,
        ];
    }

    private static function botFormation(int $teamId): string
    {
        // Cada bot tiene una formación "preferida" estable según su id
        $list = ['4-4-2', '4-4-2', '3-5-2', '4-5-1', '5-3-2', '4-3-3'];
        return $list[$teamId % count($list)];
    }

    private static function botTactic(): string
    {
        $r = mt_rand(1, 100);
        return $r <= 70 ? 'normal' : ($r <= 80 ? 'pressing' : ($r <= 88 ? 'counter' : ($r <= 94 ? 'aim' : 'aow')));
    }
}
