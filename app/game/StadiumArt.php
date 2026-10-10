<?php
declare(strict_types=1);

/**
 * Dibujo caricaturesco del estadio (SVG generado al vuelo).
 *
 * El modelo depende de la capacidad total (campo de barrio, estadio municipal, moderno, gran
 * estadio, de élite y coloso) y del tipo de localidades construidas: gradas de pie (hormigón con
 * aficionados de pie, sobre todo en los fondos), asientos básicos (filas de colores), asientos
 * cubiertos (tribunas con techo) y palcos VIP (cristalera dorada en la tribuna principal).
 * Además se dibujan torres de focos, marcador, árboles, la afición del último partido y las grúas
 * cuando hay obras.
 */
final class StadiumArt
{
    /** [capacidad mínima, nombre del modelo, descripción] */
    public const MODELS = [
        [0,     'Campo de barrio',   'Una tribunita de madera junto al campo. Aquí empiezan las leyendas.'],
        [3000,  'Estadio municipal', 'Gradas a ambos lados del campo y una pequeña tribuna principal.'],
        [10000, 'Estadio moderno',   'Gradas en los cuatro lados y torres de focos.'],
        [25000, 'Gran estadio',      'Gradas de dos pisos, marcador electrónico y ambiente de primera.'],
        [50000, 'Estadio de élite',  'Un estadio cerrado de tres anillos digno de las grandes noches.'],
        [80000, 'Coloso',            'Un templo del fútbol que impone a cualquier rival.'],
    ];

    private const PALETTE = [
        ['#d62828', '#8f1515'], ['#1d4ed8', '#123189'], ['#16a34a', '#0d6b30'], ['#f59e0b', '#b45309'],
        ['#7c3aed', '#4c1d95'], ['#0891b2', '#0e5d73'], ['#db2777', '#8f1450'], ['#111827', '#000000'],
        ['#ea580c', '#9a3412'], ['#0f766e', '#064e47'],
    ];

    private const C = [400.0, 250.0];
    /** Campo en perspectiva: lejano, cercano */
    private const PITCH = [[285.0, 192.0], [515.0, 192.0], [565.0, 308.0], [235.0, 308.0]];

    /** Altura (en px) que ganan las gradas hacia atrás, según el modelo */
    private const HEIGHT = [16, 22, 32, 44, 56, 66];

    private static float $k1 = 1.06;
    private static float $k2 = 1.5;
    private static float $lmax = 20;

    private array $seats;
    private int $cap;
    private float $occupancy;
    private string $color;
    private string $dark;
    private int $seed;

    public static function model(int $capacity): int
    {
        $idx = 0;
        foreach (self::MODELS as $i => [$min]) {
            if ($capacity >= $min) {
                $idx = $i;
            }
        }
        return $idx;
    }

    public static function modelName(int $capacity): string
    {
        return t(self::MODELS[self::model($capacity)][1]);
    }

    public static function modelDescription(int $capacity): string
    {
        return t(self::MODELS[self::model($capacity)][2]);
    }

    /**
     * @param array $seats ['terraces'=>n, 'basic'=>n, 'roof'=>n, 'vip'=>n]
     * @param float|null $occupancy 0..1 (afición del último partido); null = sin público
     */
    public static function svg(array $seats, int $teamId, ?float $occupancy = 0.6, bool $building = false, string $title = ''): string
    {
        $art = new self();
        $art->seats = array_map('intval', $seats + ['terraces' => 0, 'basic' => 0, 'roof' => 0, 'vip' => 0]);
        $art->cap = max(0, array_sum($art->seats));
        $art->occupancy = $occupancy === null ? 0.0 : max(0.0, min(1.0, $occupancy));
        [$art->color, $art->dark] = self::PALETTE[abs($teamId) % count(self::PALETTE)];
        $art->seed = $teamId * 7919 + $art->cap;
        return $art->draw($building, $title);
    }

    /* --------------------------------------------------------------- */

    private function rnd(): float
    {
        // Generador determinista (el dibujo no cambia al recargar)
        $this->seed = ($this->seed * 1103515245 + 12345) & 0x7fffffff;
        return $this->seed / 0x7fffffff;
    }

    private static function sc(array $p, float $k): array
    {
        return [self::C[0] + ($p[0] - self::C[0]) * $k, self::C[1] + ($p[1] - self::C[1]) * $k];
    }

    /** Peso de la elevación: las gradas del fondo del dibujo se ven más altas que las cercanas */
    private static function weight(array $p): float
    {
        $w = ($p[1] - self::PITCH[2][1]) / (self::PITCH[0][1] - self::PITCH[2][1]);
        return -0.45 + 1.45 * max(0.0, min(1.0, $w));
    }

    /** Punto de la grada: punto $p del borde del campo, a la distancia $k, elevado según la altura de la grada */
    private static function gp(array $p, float $k, float $extra = 0.0): array
    {
        $q = self::sc($p, $k);
        $f = (self::$k2 - self::$k1) > 0 ? max(0.0, min(1.0, ($k - self::$k1) / (self::$k2 - self::$k1))) : 0;
        $q[1] -= (self::$lmax * $f + $extra) * self::weight($p);
        return $q;
    }

    private static function pts(array $pts): string
    {
        return implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $pts));
    }

    private static function lerp(array $a, array $b, float $t): array
    {
        return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
    }

    private function draw(bool $building, string $title): string
    {
        $cap = $this->cap;
        $model = self::model($cap);
        $s = $this->seats;
        $terrShare = $cap ? $s['terraces'] / $cap : 0;
        $roofShare = $cap ? ($s['roof'] + $s['vip']) / $cap : 0;

        // Qué tribunas existen: 0 lejana (principal), 1 derecha, 2 cercana, 3 izquierda
        $sides = match (true) {
            $model === 0 => [0],
            $model === 1 => [0, 2],
            default => [0, 1, 2, 3],
        };
        $bowl = $model >= 4;
        $tiers = $model >= 4 ? 3 : ($model === 3 ? 2 : 1);
        $depth = $model === 0 ? 0.2 : min(0.7, 0.16 + 0.5 * sqrt($cap / 100000));
        $k1 = 1.06;
        $k2 = $k1 + $depth;
        self::$k1 = $k1;
        self::$k2 = $k2;
        self::$lmax = self::HEIGHT[$model];

        // Tipo de cada tribuna: terraces / seats; techos
        $standType = [];
        foreach ($sides as $i) {
            $isEnd = $i === 1 || $i === 3;
            $standType[$i] = 'seats';
            if ($s['terraces'] > 0 && (($isEnd && $terrShare > 0.12) || ($i === 2 && $terrShare > 0.45) || ($i === 0 && $terrShare > 0.8) || count($sides) === 1 && $terrShare > 0.5)) {
                $standType[$i] = 'terraces';
            }
        }
        $roofCount = 0;
        if ($roofShare > 0) {
            $roofCount = max(1, min(count($sides), (int)ceil($roofShare * count($sides) * 2.2)));
        }
        if ($bowl && $roofShare > 0.35) {
            $roofCount = count($sides);
        }
        $roofed = array_slice([0, 2, 1, 3], 0, $roofCount);
        $roofed = array_values(array_intersect($roofed, $sides));

        $o = [];
        $o[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" class="stadium-art" role="img" aria-label="' . e($title) . '">';
        $o[] = '<defs>'
            . '<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cc4f2"/><stop offset="1" stop-color="#d8f0ff"/></linearGradient>'
            . '<linearGradient id="grass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8fd16a"/><stop offset="1" stop-color="#5fae46"/></linearGradient>'
            . '<radialGradient id="glow"><stop offset="0" stop-color="#fff7b0" stop-opacity=".95"/><stop offset="1" stop-color="#fff7b0" stop-opacity="0"/></radialGradient>'
            . '<pattern id="stripes" width="40" height="40" patternUnits="userSpaceOnUse" patternTransform="skewX(-12)"><rect width="20" height="40" fill="#4fae3e"/><rect x="20" width="20" height="40" fill="#46a136"/></pattern>'
            . '</defs>';
        $o[] = '<rect width="800" height="450" fill="url(#sky)"/>';
        // Nubes
        foreach ([[110, 55, 1.0], [640, 40, 0.8], [420, 28, 0.6]] as [$cx, $cy, $z]) {
            $o[] = sprintf('<g fill="#fff" opacity=".9"><ellipse cx="%d" cy="%d" rx="%d" ry="%d"/><ellipse cx="%d" cy="%d" rx="%d" ry="%d"/><ellipse cx="%d" cy="%d" rx="%d" ry="%d"/></g>',
                $cx, $cy, 34 * $z, 14 * $z, $cx - 26 * $z, $cy + 5, 22 * $z, 11 * $z, $cx + 28 * $z, $cy + 5, 24 * $z, 11 * $z);
        }
        // Colinas y suelo
        $o[] = '<path d="M0 120 Q120 70 250 112 T520 104 T800 110 V450 H0Z" fill="#a8dc8a"/>';
        $o[] = '<path d="M0 140 Q200 118 400 136 T800 132 V450 H0Z" fill="url(#grass)"/>';
        // Explanada alrededor del estadio
        $plaza = array_map(fn($p) => self::sc($p, $k2 + 0.18), self::PITCH);
        $o[] = '<polygon points="' . self::pts($plaza) . '" fill="#d9d2bf" stroke="#b9ae93" stroke-width="3" stroke-linejoin="round"/>';
        $this->trees($o, $k2, $model);

        // Fachadas (la parte exterior de las gradas, que se ve sobre todo en la grada cercana)
        foreach ($sides as $i) {
            $this->facade($o, $i, $k2, $model, $bowl);
        }
        // Tribunas en orden de dibujo: lejana, laterales, (campo), cercana
        foreach ([0, 1, 3] as $i) {
            if (in_array($i, $sides, true)) {
                $this->stand($o, $i, $k1, $k2, $standType[$i], in_array($i, $roofed, true), $tiers, $model);
            }
        }
        $this->pitch($o, $model);
        if (in_array(2, $sides, true)) {
            $this->stand($o, 2, $k1, $k2, $standType[2], in_array(2, $roofed, true), $tiers, $model);
        }
        // Palcos VIP en la tribuna principal
        if ($s['vip'] > 0 && in_array(0, $sides, true)) {
            $this->vip($o, $k1, $k2, $s['vip']);
        }
        // Focos
        if ($model >= 2) {
            foreach ([0, 1, 2, 3] as $i) {
                $this->floodlight($o, self::sc(self::PITCH[$i], $k2 + 0.12), $model);
            }
        } else {
            foreach ([0, 1] as $i) {
                $this->lampPost($o, self::sc(self::PITCH[$i], $k1 + 0.2));
            }
        }
        if ($model >= 3) {
            $this->scoreboard($o, self::gp(self::PITCH[1], $k2 + 0.02));
        }
        // Bandera del club en la tribuna principal
        $top = self::gp(self::lerp(self::PITCH[0], self::PITCH[1], 0.5), $k2, in_array(0, $roofed, true) ? 16 + 3 * $model : 0);
        $this->flag($o, $top[0], $top[1] - 6);
        if ($building) {
            $this->crane($o, self::sc(self::PITCH[2], $k2 + 0.08));
        }
        $o[] = '</svg>';
        return implode('', $o);
    }

    private function trees(array &$o, float $k2, int $model): void
    {
        $spots = [[40, 190], [90, 230], [60, 300], [740, 200], [700, 250], [760, 320], [130, 395], [670, 405], [30, 420], [770, 430], [200, 150], [610, 150]];
        foreach ($spots as $j => [$x, $y]) {
            $p = [$x, $y];
            // Fuera de la explanada
            if (abs($x - 400) < (180 + 160 * ($k2 - 1)) && $y > 120 && $y < 250 + 70 * $k2) {
                continue;
            }
            $r = 14 + ($j % 3) * 4;
            $o[] = sprintf('<g stroke="#2d3b23" stroke-width="2.5"><rect x="%.1f" y="%.1f" width="6" height="%d" fill="#8a5a2b"/><circle cx="%.1f" cy="%.1f" r="%d" fill="%s"/></g>',
                $p[0] - 3, $p[1], $r, $p[0], $p[1] - 2, $r, $j % 2 ? '#3f9b3a' : '#57b246');
        }
    }

    private function pitch(array &$o, int $model): void
    {
        $P = self::PITCH;
        $o[] = '<polygon points="' . self::pts(array_map(fn($p) => self::sc($p, 1.05), $P)) . '" fill="#3e8f35" stroke="#2b2b2b" stroke-width="3" stroke-linejoin="round"/>';
        $o[] = '<polygon points="' . self::pts($P) . '" fill="url(#stripes)"/>';
        $w = 'stroke="#fff" stroke-width="2.4" fill="none" stroke-linejoin="round"';
        $o[] = '<polygon points="' . self::pts($P) . '" ' . $w . '/>';
        $mL = self::lerp($P[0], $P[3], 0.5);
        $mR = self::lerp($P[1], $P[2], 0.5);
        $o[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" %s/>', $mL[0], $mL[1], $mR[0], $mR[1], $w);
        $o[] = sprintf('<ellipse cx="400" cy="%.1f" rx="34" ry="13" %s/>', $mL[1], $w);
        // Áreas
        foreach ([[0, 3, 1], [1, 2, -1]] as [$a, $b, $dir]) {
            $t1 = self::lerp($P[$a], $P[$b], 0.22);
            $t2 = self::lerp($P[$a], $P[$b], 0.78);
            $in = 46 * $dir;
            $o[] = sprintf('<polyline points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" %s/>', $t1[0], $t1[1], $t1[0] + $in, $t1[1] + 2, $t2[0] + $in * 1.15, $t2[1] - 2, $t2[0], $t2[1], $w);
            // Porterías
            $g1 = self::lerp($P[$a], $P[$b], 0.42);
            $g2 = self::lerp($P[$a], $P[$b], 0.58);
            $o[] = sprintf('<polyline points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" stroke="#fff" stroke-width="3.2" fill="rgba(255,255,255,.35)"/>', $g1[0], $g1[1], $g1[0] - 9 * $dir, $g1[1] - 4, $g2[0] - 9 * $dir, $g2[1] - 4, $g2[0], $g2[1]);
        }
        if ($model === 0) {
            // Valla de madera alrededor del campo
            $f = array_map(fn($p) => self::sc($p, 1.08), $P);
            $o[] = '<polygon points="' . self::pts($f) . '" fill="none" stroke="#8a5a2b" stroke-width="3" stroke-dasharray="7 4"/>';
        }
    }

    /** Polígono de una franja de la tribuna $side entre los factores ka y kb (y del tramo ta..tb del lado) */
    private static function band(int $side, float $ka, float $kb, float $ta = 0.0, float $tb = 1.0): array
    {
        $A = self::PITCH[$side];
        $B = self::PITCH[($side + 1) % 4];
        $a = self::lerp($A, $B, $ta);
        $b = self::lerp($A, $B, $tb);
        // Se subdivide para que la elevación (que varía a lo largo del lado) se vea curva y suave
        $n = 6;
        $inner = [];
        $outer = [];
        for ($i = 0; $i <= $n; $i++) {
            $p = self::lerp($a, $b, $i / $n);
            $inner[] = self::gp($p, $ka);
            $outer[] = self::gp($p, $kb);
        }
        return array_merge($inner, array_reverse($outer));
    }

    private function stand(array &$o, int $side, float $k1, float $k2, string $type, bool $roof, int $tiers, int $model): void
    {
        $isEnd = $side === 1 || $side === 3;
        // Los fondos de los estadios pequeños son algo menos profundos
        $kEnd = $isEnd && $model < 4 ? $k1 + ($k2 - $k1) * 0.8 : $k2;
        $ta = 0.0;
        $tb = 1.0;
        if ($model === 0) {
            [$ta, $tb] = [0.25, 0.75];
        } elseif ($model === 1 && $side === 2) {
            [$ta, $tb] = [0.12, 0.88];
        } elseif ($model >= 2 && $model <= 3) {
            [$ta, $tb] = [0.035, 0.965];
        }
        // Base / estructura
        $base = self::band($side, $k1, $kEnd + 0.012, $ta, $tb);
        $structure = $model === 0 ? '#a0703e' : '#9aa3ad';
        $o[] = '<polygon points="' . self::pts($base) . '" fill="' . $structure . '" stroke="#2b2b2b" stroke-width="3" stroke-linejoin="round"/>';

        $rows = max(3, (int)round(($kEnd - $k1) / 0.032));
        $tierBreaks = [];
        for ($t = 1; $t < $tiers; $t++) {
            $tierBreaks[] = (int)round($rows * $t / $tiers);
        }
        for ($r = 0; $r < $rows; $r++) {
            $ka = $k1 + ($kEnd - $k1) * $r / $rows;
            $kb = $k1 + ($kEnd - $k1) * ($r + 1) / $rows;
            if ($type === 'terraces') {
                $fill = $r % 2 ? '#c9c7bd' : '#b5b2a6';
            } elseif ($model === 0) {
                $fill = $r % 2 ? '#c98d52' : '#b77b42';
            } elseif ($roof) {
                $fill = $r % 2 ? $this->dark : self::shade($this->dark, 20);
            } else {
                $fill = $r % 2 ? $this->color : self::shade($this->color, 28);
            }
            $poly = self::band($side, $ka + 0.003, $kb - 0.003, $ta + 0.01, $tb - 0.01);
            $o[] = '<polygon points="' . self::pts($poly) . '" fill="' . $fill . '"/>';
            if (in_array($r, $tierBreaks, true)) {
                $l = self::band($side, $ka, $ka + 0.012, $ta, $tb);
                $o[] = '<polygon points="' . self::pts($l) . '" fill="#3b4048"/>';
            }
            $this->crowdRow($o, $side, ($ka + $kb) / 2, $ta, $tb, $type === 'terraces');
        }
        // Escaleras (pasillos) en las tribunas grandes
        if ($model >= 2) {
            foreach ([0.2, 0.4, 0.6, 0.8] as $tt) {
                if ($tt <= $ta || $tt >= $tb) {
                    continue;
                }
                $p1 = self::gp(self::lerp(self::PITCH[$side], self::PITCH[($side + 1) % 4], $tt), $k1);
                $p2 = self::gp(self::lerp(self::PITCH[$side], self::PITCH[($side + 1) % 4], $tt), $kEnd);
                $o[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#e8e6df" stroke-width="3"/>', $p1[0], $p1[1], $p2[0], $p2[1]);
            }
        }
        $o[] = '<polygon points="' . self::pts(self::band($side, $k1, $kEnd + 0.012, $ta, $tb)) . '" fill="none" stroke="#2b2b2b" stroke-width="3" stroke-linejoin="round"/>';
        if ($roof) {
            $this->roof($o, $side, $k1, $kEnd, $ta, $tb, $model);
        }
    }

    private function crowdRow(array &$o, int $side, float $k, float $ta, float $tb, bool $standing): void
    {
        if ($this->occupancy <= 0) {
            return;
        }
        $A0 = self::lerp(self::PITCH[$side], self::PITCH[($side + 1) % 4], $ta);
        $B0 = self::lerp(self::PITCH[$side], self::PITCH[($side + 1) % 4], $tb);
        $A = self::gp($A0, $k);
        $B = self::gp($B0, $k);
        $len = hypot($B[0] - $A[0], $B[1] - $A[1]);
        $n = (int)($len / 7);
        $colors = [$this->color, '#ffffff', '#f4d35e', $this->dark, '#f28482', '#222222'];
        $dots = '';
        for ($i = 0; $i < $n; $i++) {
            if ($this->rnd() > $this->occupancy) {
                continue;
            }
            $p = self::gp(self::lerp($A0, $B0, ($i + 0.5 + ($this->rnd() - 0.5) * 0.6) / $n), $k);
            $c = $colors[(int)($this->rnd() * count($colors))];
            $dots .= sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>', $p[0], $p[1] - ($standing ? 1.5 : 0.5), $standing ? 2.4 : 2.1, $c);
        }
        if ($dots) {
            $o[] = '<g stroke="#2b2b2b" stroke-width=".6">' . $dots . '</g>';
        }
    }

    private function roof(array &$o, int $side, float $k1, float $k2, float $ta, float $tb, int $model): void
    {
        $front = $k1 + ($k2 - $k1) * 0.5;
        $h = 16 + 3 * $model;
        $A = self::PITCH[$side];
        $B = self::PITCH[($side + 1) % 4];
        $n = 6;
        $fr = [];
        $bk = [];
        for ($i = 0; $i <= $n; $i++) {
            $p = self::lerp($A, $B, $ta - 0.01 + ($tb - $ta + 0.02) * $i / $n);
            $top = self::gp($p, $k2 + 0.02, $h);
            $f = self::gp($p, $front);
            $fr[] = [$f[0], $top[1] + ($f[1] - self::gp($p, $k2)[1]) * 0.35];
            $bk[] = $top;
        }
        foreach ([0, 3, 6] as $i) {
            $base = self::gp(self::lerp($A, $B, $ta - 0.01 + ($tb - $ta + 0.02) * $i / $n), $k2 + 0.02);
            $o[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#4b5563" stroke-width="4"/>', $base[0], $base[1], $bk[$i][0], $bk[$i][1]);
        }
        $poly = array_merge($fr, array_reverse($bk));
        $o[] = '<polygon points="' . self::pts($poly) . '" fill="#e5ebf0" fill-opacity=".88" stroke="#2b2b2b" stroke-width="3" stroke-linejoin="round"/>';
        for ($i = 1; $i < $n; $i++) {
            $o[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#9aa5b1" stroke-width="1.5"/>', $fr[$i][0], $fr[$i][1], $bk[$i][0], $bk[$i][1]);
        }
        $o[] = '<polyline points="' . self::pts($fr) . '" fill="none" stroke="' . $this->color . '" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    /** Muro exterior de la grada */
    private function facade(array &$o, int $side, float $k2, int $model, bool $bowl): void
    {
        [$ta, $tb] = match (true) {
            $model === 0 => [0.25, 0.75],
            $model === 1 && $side === 2 => [0.12, 0.88],
            $model >= 2 && $model <= 3 => [0.035, 0.965],
            default => [0.0, 1.0],
        };
        $A = self::PITCH[$side];
        $B = self::PITCH[($side + 1) % 4];
        $n = 6;
        $top = [];
        $bottom = [];
        for ($i = 0; $i <= $n; $i++) {
            $p = self::lerp($A, $B, $ta + ($tb - $ta) * $i / $n);
            $top[] = self::gp($p, $k2 + 0.012);
            $bottom[] = self::sc($p, $k2 + 0.012);
        }
        $color = $model === 0 ? '#8a5a2b' : self::shade($this->dark, -10);
        $o[] = '<polygon points="' . self::pts(array_merge($top, array_reverse($bottom))) . '" fill="' . $color . '" stroke="#2b2b2b" stroke-width="3" stroke-linejoin="round"/>';
        if ($model >= 2) {
            for ($i = 0; $i < $n; $i++) {
                $b = self::lerp($bottom[$i], $bottom[$i + 1], 0.5);
                $t = self::lerp($top[$i], $top[$i + 1], 0.5);
                $hh = ($b[1] - $t[1]) * 0.55;
                if ($hh > 8) {
                    $o[] = sprintf('<path d="M%.1f %.1f v-%.1f a5 5 0 0 1 10 0 v%.1f z" fill="#1f2937" opacity=".75"/>', $b[0] - 5, $b[1], $hh - 5, $hh - 5);
                }
            }
        }
    }

    private function vip(array &$o, float $k1, float $k2, int $vip): void
    {
        $len = max(0.22, min(0.8, 0.22 + $vip / 2500));
        $ta = 0.5 - $len / 2;
        $tb = 0.5 + $len / 2;
        $mid = $k1 + ($k2 - $k1) * 0.55;
        $poly = self::band(0, $mid, $mid + 0.07, $ta, $tb);
        $o[] = '<polygon points="' . self::pts($poly) . '" fill="#bfe6ff" stroke="#c8961e" stroke-width="3" stroke-linejoin="round"/>';
        $n = max(3, (int)round($len * 14));
        for ($i = 1; $i < $n; $i++) {
            $p1 = self::lerp($poly[0], $poly[1], $i / $n);
            $p2 = self::lerp($poly[3], $poly[2], $i / $n);
            $o[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#ffffff" stroke-width="1.6"/>', $p1[0], $p1[1], $p2[0], $p2[1]);
        }
        $c = self::lerp($poly[0], $poly[1], 0.5);
        $o[] = sprintf('<text x="%.1f" y="%.1f" font-size="9" font-weight="bold" fill="#8a6412" text-anchor="middle" font-family="sans-serif">VIP</text>', $c[0], $c[1] - 2);
    }

    private function floodlight(array &$o, array $p, int $model): void
    {
        $h = 70 + 12 * $model;
        [$x, $y] = $p;
        $o[] = sprintf('<circle cx="%.1f" cy="%.1f" r="26" fill="url(#glow)"/>', $x, $y - $h);
        $o[] = sprintf('<g stroke="#2b2b2b" stroke-width="2.5"><path d="M%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f Z" fill="#6b7280"/>'
            . '<rect x="%.1f" y="%.1f" width="28" height="16" rx="3" fill="#374151"/></g>',
            $x - 5, $y, $x - 2, $y - $h, $x + 2, $y - $h, $x + 5, $y, $x - 14, $y - $h - 14);
        for ($i = 0; $i < 3; $i++) {
            $o[] = sprintf('<circle cx="%.1f" cy="%.1f" r="3" fill="#fff7b0"/>', $x - 7 + $i * 7, $y - $h - 6);
        }
    }

    private function lampPost(array &$o, array $p): void
    {
        [$x, $y] = $p;
        $o[] = sprintf('<g stroke="#2b2b2b" stroke-width="2"><line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke-width="3"/><circle cx="%.1f" cy="%.1f" r="5" fill="#fff7b0"/></g>', $x, $y, $x, $y - 45, $x, $y - 48);
    }

    private function scoreboard(array &$o, array $p): void
    {
        [$x, $y] = $p;
        $o[] = sprintf('<g stroke="#2b2b2b" stroke-width="2.5"><line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke-width="4"/>'
            . '<rect x="%.1f" y="%.1f" width="54" height="26" rx="4" fill="#1f2937"/></g>'
            . '<text x="%.1f" y="%.1f" font-size="13" font-family="monospace" font-weight="bold" fill="#facc15" text-anchor="middle">2-1</text>',
            $x + 20, $y, $x + 20, $y - 30, $x - 7, $y - 56, $x + 20, $y - 38);
    }

    private function flag(array &$o, float $x, float $y): void
    {
        $o[] = sprintf('<g stroke="#2b2b2b" stroke-width="2"><line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>'
            . '<path d="M%.1f %.1f q10 -4 20 0 t20 0 v14 q-10 4 -20 0 t-20 0z" fill="%s"/></g>',
            $x, $y + 22, $x, $y - 18, $x, $y - 18, $this->color);
    }

    private function crane(array &$o, array $p): void
    {
        [$x, $y] = $p;
        $x = min(740, $x + 30);
        $o[] = sprintf('<g stroke="#2b2b2b" stroke-width="2.5" fill="#f6b916">'
            . '<rect x="%.1f" y="%.1f" width="12" height="150"/>'
            . '<rect x="%.1f" y="%.1f" width="150" height="10"/>'
            . '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke-dasharray="3 3"/>'
            . '<rect x="%.1f" y="%.1f" width="18" height="14" fill="#9ca3af"/>'
            . '<path d="M%.1f %.1f l10 -18 l10 18z" fill="#f97316"/><path d="M%.1f %.1f l10 -18 l10 18z" fill="#f97316"/></g>',
            $x, $y - 150, $x - 120, $y - 150, $x - 100, $y - 140, $x - 100, $y - 80, $x - 109, $y - 80,
            $x - 60, $y + 4, $x - 30, $y + 4);
    }

    private static function shade(string $hex, int $amt): string
    {
        $hex = ltrim($hex, '#');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $v = hexdec(substr($hex, $i * 2, 2));
            $v = max(0, min(255, $v + $amt));
            $out .= str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}
