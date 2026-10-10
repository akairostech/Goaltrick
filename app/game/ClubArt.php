<?php
declare(strict_types=1);

/**
 * Uniformes y escudos caricaturescos generados en SVG.
 *
 * Hay 1000 uniformes (camiseta + pantalón) y 1000 escudos predeterminados. Cada número (1..1000)
 * corresponde siempre al mismo diseño, así que no hace falta guardar imágenes: se dibujan al vuelo
 * (art.php?k=kit&n=123). Los clubes también pueden subir su propia imagen.
 *
 * Uniformes: 10 diseños de camiseta × 100 combinaciones de colores, con mangas y pantalón variados.
 * Escudos:   10 formas × 10 particiones × 10 símbolos, con colores variados.
 */
final class ClubArt
{
    public const COUNT = 1000;

    /** Paleta: clave => [color, nombre en español] */
    public const COLORS = [
        'red'    => ['#d6312b', 'Rojo'],
        'wine'   => ['#7d1d2f', 'Granate'],
        'orange' => ['#f07f13', 'Naranja'],
        'yellow' => ['#f7c51e', 'Amarillo'],
        'gold'   => ['#c49a2c', 'Dorado'],
        'green'  => ['#3fa34d', 'Verde'],
        'dgreen' => ['#17602a', 'Verde oscuro'],
        'sky'    => ['#5bc0eb', 'Celeste'],
        'blue'   => ['#1f5fc4', 'Azul'],
        'navy'   => ['#172b5e', 'Azul marino'],
        'purple' => ['#6b2fa0', 'Morado'],
        'pink'   => ['#e8457c', 'Rosa'],
        'white'  => ['#ffffff', 'Blanco'],
        'black'  => ['#242424', 'Negro'],
        'grey'   => ['#9aa1a6', 'Gris'],
        'brown'  => ['#7a4b26', 'Marrón'],
    ];

    public const KIT_PATTERNS = [
        'Lisa', 'Rayas verticales', 'Franjas horizontales', 'Mitades', 'Banda diagonal',
        'Pico (V)', 'Cuartos', 'Rayas finas', 'Franja central', 'Hombros y costados',
    ];
    public const CREST_SHAPES = [
        'Escudo clásico', 'Círculo', 'Escudo francés', 'Insignia', 'Rombo',
        'Óvalo', 'Hexágono', 'Escudo suizo', 'Estandarte', 'Octógono',
    ];
    public const CREST_SYMBOLS = [
        'Balón', 'Estrella', 'Corona', 'Rayo', 'Torre', 'Olas', 'Águila', 'Árbol', 'Ancla', 'Sol',
    ];

    private const INK = '#1d2a1b';

    /** Diseño por defecto de un club (si no ha elegido ninguno) */
    public static function defaultFor(int $teamId, string $what): int
    {
        $mul = $what === 'kit' ? 337 : 571;
        return ($teamId * $mul + ($what === 'kit' ? 11 : 29)) % self::COUNT + 1;
    }

    /* ------------------------------------------------------------------
     *  Combinaciones de colores
     * ------------------------------------------------------------------ */

    private static function lum(string $hex): float
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }

    /** Parejas de colores con buen contraste, siempre en el mismo orden */
    private static function pairs(): array
    {
        static $pairs = null;
        if ($pairs !== null) {
            return $pairs;
        }
        $keys = array_keys(self::COLORS);
        $pairs = [];
        foreach ($keys as $a) {
            foreach ($keys as $b) {
                if ($a !== $b && abs(self::lum(self::COLORS[$a][0]) - self::lum(self::COLORS[$b][0])) >= 0.22) {
                    $pairs[] = [$a, $b];
                }
            }
        }
        return $pairs;
    }

    /** Datos de un uniforme: [diseño, color1, color2, mangas, pantalón] (claves de COLORS) */
    public static function kitSpec(int $n): array
    {
        $i = max(0, min(self::COUNT - 1, $n - 1));
        $pattern = $i % 10;
        $c = intdiv($i, 10);                       // 0..99
        $pairs = self::pairs();
        $len = count($pairs);
        [$p, $s] = $pairs[($c * 67 + $pattern * 7) % $len];
        $sleeve = [$p, $s, $p][($c + $pattern) % 3];
        $shorts = [$p, $s, 'white', 'black', $s][($c * 3 + $pattern) % 5];
        if ($shorts === $p && $pattern === 0) {
            $shorts = $s;
        }
        return ['pattern' => $pattern, 'primary' => $p, 'secondary' => $s, 'sleeve' => $sleeve, 'shorts' => $shorts,
                'cuffs' => ($c + $pattern) % 3 === 2];
    }

    /** Datos de un escudo */
    public static function crestSpec(int $n): array
    {
        $i = max(0, min(self::COUNT - 1, $n - 1));
        $pairs = self::pairs();
        [$p, $s] = $pairs[($i * 37 + intdiv($i, 100) * 11) % count($pairs)];
        $shape = $i % 10;
        $division = intdiv($i, 10) % 10;
        return ['shape' => $shape, 'division' => $division, 'symbol' => (intdiv($i, 100) + $shape + $division) % 10,
                'primary' => $p, 'secondary' => $s];
    }

    private static function hex(string $key): string
    {
        return self::COLORS[$key][0] ?? '#888888';
    }

    /* ------------------------------------------------------------------
     *  Uniforme
     * ------------------------------------------------------------------ */

    public static function kitSvg(int $n, int $size = 200): string
    {
        $k = self::kitSpec($n);
        $p = self::hex($k['primary']);
        $s = self::hex($k['secondary']);
        $sl = self::hex($k['sleeve']);
        $sh = self::hex($k['shorts']);
        $ink = self::INK;
        $torso = 'M55,30 L83,16 Q100,30 117,16 L145,30 L145,150 Q100,158 55,150 Z';
        $body = 'M60,22 L84,14 Q100,28 116,14 L140,22 L178,48 L160,82 L145,72 L145,150 Q100,158 55,150 L55,72 L40,82 L22,48 Z';
        $lsleeve = 'M60,22 L22,48 L40,82 L55,72 L55,34 Z';
        $rsleeve = 'M140,22 L178,48 L160,82 L145,72 L145,34 Z';

        $pat = '';
        switch ($k['pattern']) {
            case 1: // rayas verticales
                for ($x = 61; $x < 145; $x += 22) {
                    $pat .= "<rect x=\"$x\" y=\"0\" width=\"11\" height=\"170\" fill=\"$s\"/>";
                }
                break;
            case 2: // franjas horizontales
                for ($y = 38; $y < 160; $y += 26) {
                    $pat .= "<rect x=\"0\" y=\"$y\" width=\"200\" height=\"13\" fill=\"$s\"/>";
                }
                break;
            case 3: // mitades
                $pat = "<rect x=\"100\" y=\"0\" width=\"100\" height=\"170\" fill=\"$s\"/>";
                break;
            case 4: // banda diagonal
                $pat = "<polygon points=\"55,40 80,22 150,140 125,160\" fill=\"$s\"/>";
                break;
            case 5: // pico
                $pat = "<polygon points=\"55,52 100,96 145,52 145,74 100,118 55,74\" fill=\"$s\"/>";
                break;
            case 6: // cuartos
                $pat = "<rect x=\"100\" y=\"0\" width=\"100\" height=\"88\" fill=\"$s\"/><rect x=\"0\" y=\"88\" width=\"100\" height=\"90\" fill=\"$s\"/>";
                break;
            case 7: // rayas finas
                for ($x = 62; $x < 145; $x += 10) {
                    $pat .= "<rect x=\"$x\" y=\"0\" width=\"3\" height=\"170\" fill=\"$s\"/>";
                }
                break;
            case 8: // franja central
                $pat = "<rect x=\"86\" y=\"0\" width=\"28\" height=\"170\" fill=\"$s\"/>";
                break;
            case 9: // hombros y costados
                $pat = "<rect x=\"0\" y=\"0\" width=\"200\" height=\"44\" fill=\"$s\"/><rect x=\"55\" y=\"0\" width=\"12\" height=\"170\" fill=\"$s\"/><rect x=\"133\" y=\"0\" width=\"12\" height=\"170\" fill=\"$s\"/>";
                break;
        }
        $cuff = $k['cuffs'] ? "<polygon points=\"22,48 30,43 47,77 40,82\" fill=\"$s\" stroke=\"$ink\" stroke-width=\"2\"/><polygon points=\"178,48 170,43 153,77 160,82\" fill=\"$s\" stroke=\"$ink\" stroke-width=\"2\"/>" : '';
        $collarCol = $k['pattern'] === 0 ? $s : ($k['sleeve'] === $k['primary'] ? $s : $p);
        $shortsStripe = $k['shorts'] === $k['secondary'] ? $p : $s;
        $num = (($n * 7) % 23) + 1;
        $numCol = self::lum($p) > 0.6 ? self::hex('navy') : '#ffffff';
        $numStroke = $numCol === '#ffffff' ? $ink : '#ffffff';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 230" width="' . $size . '" height="' . round($size * 1.15) . '">'
            . '<defs><clipPath id="t"><path d="' . $torso . '"/></clipPath></defs>'
            . '<ellipse cx="100" cy="222" rx="62" ry="6" fill="#000" opacity=".12"/>'
            // pantalón
            . "<path d=\"M57,156 L143,156 L149,212 L108,214 L100,186 L92,214 L51,212 Z\" fill=\"$sh\" stroke=\"$ink\" stroke-width=\"5\" stroke-linejoin=\"round\"/>"
            . "<path d=\"M57,166 L143,166\" stroke=\"$shortsStripe\" stroke-width=\"5\"/>"
            . "<path d=\"M53,200 L58,166 M147,200 L142,166\" stroke=\"$shortsStripe\" stroke-width=\"4\" stroke-linecap=\"round\"/>"
            // camiseta
            . "<path d=\"$body\" fill=\"$p\"/>"
            . "<path d=\"$lsleeve\" fill=\"$sl\"/><path d=\"$rsleeve\" fill=\"$sl\"/>"
            . '<g clip-path="url(#t)">' . $pat . '</g>'
            . $cuff
            . "<path d=\"$body\" fill=\"none\" stroke=\"$ink\" stroke-width=\"5\" stroke-linejoin=\"round\"/>"
            . "<path d=\"M55,34 L55,72 M145,34 L145,72\" stroke=\"$ink\" stroke-width=\"3\" opacity=\".55\"/>"
            . "<path d=\"M84,14 Q100,30 116,14 Q100,40 84,14 Z\" fill=\"$collarCol\" stroke=\"$ink\" stroke-width=\"4\" stroke-linejoin=\"round\"/>"
            . "<text x=\"100\" y=\"118\" text-anchor=\"middle\" font-family=\"Arial Black,Arial,sans-serif\" font-weight=\"900\" font-size=\"40\" fill=\"$numCol\" stroke=\"$numStroke\" stroke-width=\"1.5\" opacity=\".9\">$num</text>"
            . '<path d="M66,40 Q70,90 64,140" stroke="#fff" stroke-width="5" opacity=".18" fill="none" stroke-linecap="round"/>'
            . '</svg>';
    }

    /* ------------------------------------------------------------------
     *  Caras de los jugadores con el uniforme del club
     * ------------------------------------------------------------------ */

    public const FACE_COUNT = 400;

    /**
     * Cara nº $face (1..400) vistiendo la camiseta del uniforme nº $kit (1..1000): 400 × 1000 combinaciones
     * generadas al vuelo a partir de las caras de assets/img/avatars (la camiseta va entre las marcas KIT y
     * el cuello entre las marcas COLLAR).
     */
    public static function faceSvg(int $face, int $kit): ?string
    {
        $face = max(1, min(self::FACE_COUNT, $face));
        $file = APP_ROOT . sprintf('/assets/img/avatars/%03d.svg', $face);
        $svg = @file_get_contents($file);
        if ($svg === false) {
            return null;
        }
        $k = self::kitSpec($kit);
        $p = self::hex($k['primary']);
        $s = self::hex($k['secondary']);
        $sl = self::hex($k['sleeve']);
        $ink = '#2a2420';
        $collar = $k['pattern'] === 0 ? $s : ($k['sleeve'] === $k['primary'] ? $s : $p);
        if ($collar === $p) {
            $collar = $s;
        }
        $numCol = self::lum($p) > 0.6 ? self::hex('navy') : '#ffffff';
        $body = 'M10 100 C10 82 26 74 50 74 C74 74 90 82 90 100Z';
        $pat = match ($k['pattern']) {
            1 => implode('', array_map(fn($x) => "<rect x=\"$x\" y=\"70\" width=\"6\" height=\"32\" fill=\"$s\"/>", [17, 29, 41, 53, 65, 77])),
            2 => "<rect x=\"8\" y=\"81\" width=\"84\" height=\"5\" fill=\"$s\"/><rect x=\"8\" y=\"92\" width=\"84\" height=\"5\" fill=\"$s\"/>",
            3 => "<rect x=\"50\" y=\"70\" width=\"42\" height=\"32\" fill=\"$s\"/>",
            4 => "<polygon points=\"18,77 30,74 86,100 72,100\" fill=\"$s\"/>",
            5 => "<polygon points=\"8,80 50,92 92,80 92,87 50,99 8,87\" fill=\"$s\"/>",
            6 => "<rect x=\"50\" y=\"70\" width=\"42\" height=\"16\" fill=\"$s\"/><rect x=\"8\" y=\"86\" width=\"42\" height=\"16\" fill=\"$s\"/>",
            7 => implode('', array_map(fn($x) => "<rect x=\"$x\" y=\"70\" width=\"1.6\" height=\"32\" fill=\"$s\"/>", range(14, 86, 5))),
            8 => "<rect x=\"43\" y=\"70\" width=\"14\" height=\"32\" fill=\"$s\"/>",
            9 => "<rect x=\"8\" y=\"70\" width=\"84\" height=\"9\" fill=\"$s\"/><rect x=\"8\" y=\"70\" width=\"10\" height=\"32\" fill=\"$s\"/><rect x=\"82\" y=\"70\" width=\"10\" height=\"32\" fill=\"$s\"/>",
            default => '',
        };
        $sleeves = $k['sleeve'] !== $k['primary'] || $k['cuffs']
            ? "<path d=\"M8 102 L8 86 C11 82 15 80 20 78 L26 102Z\" fill=\"$sl\"/><path d=\"M92 102 L92 86 C89 82 85 80 80 78 L74 102Z\" fill=\"$sl\"/>"
              . "<path d=\"M20 78 L26 100 M80 78 L74 100\" stroke=\"$ink\" stroke-width=\"1.2\" opacity=\".6\"/>"
            : '';
        $num = (($kit * 7) % 23) + 1;
        $shirt = "<clipPath id=\"kc$face\"><path d=\"$body\"/></clipPath>"
            . "<path d=\"$body\" fill=\"$p\"/>"
            . "<g clip-path=\"url(#kc$face)\">$pat$sleeves</g>"
            . "<path d=\"$body\" fill=\"none\" stroke=\"$ink\" stroke-width=\"2.4\"/>"
            . "<text x=\"72\" y=\"97\" text-anchor=\"middle\" font-family=\"Arial Black, Arial, sans-serif\" font-weight=\"900\" font-size=\"10\" fill=\"$numCol\" stroke=\"$ink\" stroke-width=\"0.6\">$num</text>";
        $svg = preg_replace('#<!--KIT-->.*?<!--/KIT-->#s', $shirt, $svg, 1);
        $svg = preg_replace_callback('#<!--COLLAR-->(.*?)<!--/COLLAR-->#s', function ($m) use ($collar, $ink) {
            return preg_replace_callback('#(stroke|fill)="(\#[0-9a-fA-F]{6})"#', fn($c) => strtolower($c[2]) === $ink ? $c[0] : $c[1] . '="' . $collar . '"', $m[1]);
        }, $svg, 1);
        return $svg;
    }

    /* ------------------------------------------------------------------
     *  Escudo
     * ------------------------------------------------------------------ */

    private const SHAPES = [
        '<path d="M30,22 H170 V110 Q170,172 100,206 Q30,172 30,110 Z"/>',
        '<circle cx="100" cy="114" r="90"/>',
        '<path d="M28,22 H172 V138 Q172,158 152,170 L100,206 L48,170 Q28,158 28,138 Z"/>',
        '<rect x="24" y="26" width="152" height="176" rx="30"/>',
        '<path d="M100,14 L186,114 L100,212 L14,114 Z"/>',
        '<ellipse cx="100" cy="114" rx="74" ry="96"/>',
        '<path d="M100,16 L182,62 V164 L100,210 L18,164 V62 Z"/>',
        '<path d="M28,34 Q100,4 172,34 V116 Q172,180 100,208 Q28,180 28,116 Z"/>',
        '<path d="M32,20 H168 V170 L100,208 L32,170 Z"/>',
        '<path d="M60,18 H140 L184,62 V164 L140,208 H60 L16,164 V62 Z"/>',
    ];

    public static function crestSvg(int $n, int $size = 200): string
    {
        $c = self::crestSpec($n);
        $p = self::hex($c['primary']);
        $s = self::hex($c['secondary']);
        $ink = self::INK;
        $shape = self::SHAPES[$c['shape']];
        $div = match ($c['division']) {
            1 => "<rect x=\"100\" y=\"0\" width=\"100\" height=\"230\" fill=\"$s\"/>",
            2 => "<rect x=\"0\" y=\"0\" width=\"200\" height=\"106\" fill=\"$s\"/>",
            3 => "<polygon points=\"0,40 40,0 230,190 190,230\" fill=\"$s\"/>",
            4 => "<rect x=\"100\" y=\"0\" width=\"100\" height=\"114\" fill=\"$s\"/><rect x=\"0\" y=\"114\" width=\"100\" height=\"120\" fill=\"$s\"/>",
            5 => "<polygon points=\"0,150 100,70 200,150 200,190 100,110 0,190\" fill=\"$s\"/>",
            6 => "<rect x=\"84\" y=\"0\" width=\"32\" height=\"230\" fill=\"$s\"/><rect x=\"0\" y=\"98\" width=\"200\" height=\"32\" fill=\"$s\"/>",
            7 => "<rect x=\"44\" y=\"0\" width=\"24\" height=\"230\" fill=\"$s\"/><rect x=\"88\" y=\"0\" width=\"24\" height=\"230\" fill=\"$s\"/><rect x=\"132\" y=\"0\" width=\"24\" height=\"230\" fill=\"$s\"/>",
            8 => "<path d=\"M0,0 L200,230 M200,0 L0,230\" stroke=\"$s\" stroke-width=\"30\"/>",
            9 => "<rect x=\"0\" y=\"0\" width=\"200\" height=\"62\" fill=\"$s\"/>",
            default => '',
        };
        // Medallón para que el símbolo se lea sobre fondos partidos
        $medal = !in_array($c['division'], [0, 9], true);
        $symFill = $medal ? $p : (self::lum($p) > 0.6 ? self::hex('navy') : '#ffffff');
        $symFill2 = $medal ? $s : (self::lum($p) > 0.6 ? self::hex('gold') : self::hex('yellow'));
        $cy = $c['division'] === 9 ? 128 : 114;
        $sym = self::symbol($c['symbol'], $symFill, $symFill2);
        $starRow = '';
        if ($c['division'] === 9) {
            foreach ([70, 100, 130] as $x) {
                $starRow .= '<g transform="translate(' . $x . ',40) scale(.16)">' . self::starPath('#ffffff') . '</g>';
            }
        }
        $shapeFill = preg_replace('#/>$#', ' fill="' . $p . '"/>', $shape);
        $shapeStroke = preg_replace('#/>$#', ' fill="none" stroke="' . $ink . '" stroke-width="7" stroke-linejoin="round"/>', $shape);
        $rim = preg_replace('#/>$#', ' fill="none" stroke="#ffffff" stroke-width="3" opacity=".55"/>', $shape);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 230" width="' . $size . '" height="' . round($size * 1.15) . '">'
            . '<defs><clipPath id="c">' . $shape . '</clipPath></defs>'
            . '<ellipse cx="100" cy="222" rx="60" ry="6" fill="#000" opacity=".12"/>'
            . $shapeFill
            . '<g clip-path="url(#c)">' . $div . '</g>'
            . '<g transform="translate(100,114) scale(.88) translate(-100,-114)">' . $rim . '</g>'
            . $shapeStroke
            . $starRow
            . ($medal ? "<circle cx=\"100\" cy=\"$cy\" r=\"44\" fill=\"#ffffff\" stroke=\"$ink\" stroke-width=\"5\"/>" : '')
            . '<g transform="translate(100,' . $cy . ') scale(' . ($medal ? '.62' : '.85') . ')">' . $sym . '</g>'
            . '<path d="M48,40 Q60,30 80,28" stroke="#fff" stroke-width="6" opacity=".25" fill="none" stroke-linecap="round"/>'
            . '</svg>';
    }

    private static function starPath(string $fill): string
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $i % 2 ? 22 : 52;
            $a = -M_PI / 2 + $i * M_PI / 5;
            $pts[] = round($r * cos($a), 1) . ',' . round($r * sin($a), 1);
        }
        return '<polygon points="' . implode(' ', $pts) . '" fill="' . $fill . '" stroke="' . self::INK . '" stroke-width="6" stroke-linejoin="round"/>';
    }

    /** Símbolo centrado en (0,0), de unos 120 px */
    private static function symbol(int $i, string $f, string $f2): string
    {
        $ink = self::INK;
        $st = "stroke=\"$ink\" stroke-width=\"6\" stroke-linejoin=\"round\" stroke-linecap=\"round\"";
        return match ($i) {
            0 => "<circle r=\"50\" fill=\"#fff\" $st/><polygon points=\"0,-18 17,-6 11,15 -11,15 -17,-6\" fill=\"$ink\"/>"
                . "<path d=\"M0,-18 L0,-48 M17,-6 L46,-16 M11,15 L28,40 M-11,15 L-28,40 M-17,-6 L-46,-16\" stroke=\"$ink\" stroke-width=\"5\"/>",
            1 => self::starPath($f),
            2 => "<path d=\"M-52,30 L-58,-30 L-28,-2 L0,-44 L28,-2 L58,-30 L52,30 Z\" fill=\"$f2\" $st/><rect x=\"-52\" y=\"30\" width=\"104\" height=\"18\" rx=\"4\" fill=\"$f2\" $st/>"
                . "<circle cx=\"0\" cy=\"8\" r=\"8\" fill=\"$f\" stroke=\"$ink\" stroke-width=\"4\"/>",
            3 => "<polygon points=\"12,-56 -34,8 -4,8 -14,56 34,-10 4,-10\" fill=\"$f2\" $st/>",
            4 => "<path d=\"M-40,50 V-20 H-48 V-48 H-30 V-36 H-14 V-48 H14 V-36 H30 V-48 H48 V-20 H40 V50 Z\" fill=\"$f\" $st/>"
                . "<path d=\"M-12,50 V20 Q0,6 12,20 V50\" fill=\"$ink\"/>",
            5 => "<path d=\"M-56,-20 Q-42,-36 -28,-20 T0,-20 T28,-20 T56,-20\" fill=\"none\" stroke=\"$f\" stroke-width=\"12\" stroke-linecap=\"round\"/>"
                . "<path d=\"M-56,6 Q-42,-10 -28,6 T0,6 T28,6 T56,6\" fill=\"none\" stroke=\"$f2\" stroke-width=\"12\" stroke-linecap=\"round\"/>"
                . "<path d=\"M-56,32 Q-42,16 -28,32 T0,32 T28,32 T56,32\" fill=\"none\" stroke=\"$f\" stroke-width=\"12\" stroke-linecap=\"round\"/>",
            6 => "<path d=\"M0,-20 Q-30,-50 -62,-30 Q-40,-20 -46,-4 Q-26,-12 -20,0 Q-26,24 -10,44 L0,34 L10,44 Q26,24 20,0 Q26,-12 46,-4 Q40,-20 62,-30 Q30,-50 0,-20 Z\" fill=\"$f\" $st/>"
                . "<circle cx=\"0\" cy=\"-24\" r=\"12\" fill=\"$f2\" stroke=\"$ink\" stroke-width=\"5\"/>",
            7 => "<path d=\"M-9,54 L-6,8 L6,8 L9,54 Z\" fill=\"#8a5a2b\" $st/>"
                . "<path d=\"M0,-56 L34,-10 L18,-10 L44,24 L-44,24 L-18,-10 L-34,-10 Z\" fill=\"$f\" $st/>"
                . "<circle cx=\"-10\" cy=\"-14\" r=\"5\" fill=\"$f2\"/><circle cx=\"14\" cy=\"8\" r=\"5\" fill=\"$f2\"/><circle cx=\"4\" cy=\"-34\" r=\"4\" fill=\"$f2\"/>",
            8 => "<circle cx=\"0\" cy=\"-40\" r=\"10\" fill=\"none\" $st/><path d=\"M0,-30 V48 M-24,-12 H24 M-44,20 Q-40,48 0,48 Q40,48 44,20\" fill=\"none\" stroke=\"$f\" stroke-width=\"12\" stroke-linecap=\"round\"/>"
                . "<path d=\"M0,-30 V48 M-24,-12 H24 M-44,20 Q-40,48 0,48 Q40,48 44,20\" fill=\"none\" stroke=\"$ink\" stroke-width=\"3\" opacity=\".5\"/>",
            9 => (function () use ($f, $f2, $st) {
                $rays = '';
                for ($k = 0; $k < 12; $k++) {
                    $a = $k * M_PI / 6;
                    $rays .= '<polygon points="' . round(56 * cos($a), 1) . ',' . round(56 * sin($a), 1) . ' '
                        . round(30 * cos($a + 0.26), 1) . ',' . round(30 * sin($a + 0.26), 1) . ' '
                        . round(30 * cos($a - 0.26), 1) . ',' . round(30 * sin($a - 0.26), 1) . '" fill="' . $f2 . '" ' . $st . '/>';
                }
                return $rays . "<circle r=\"30\" fill=\"$f\" $st/>";
            })(),
            default => '',
        };
    }

    /* ------------------------------------------------------------------
     *  Imágenes subidas por los clubes
     * ------------------------------------------------------------------ */

    public const UPLOAD_DIR = 'assets/uploads';
    public const UPLOAD_LIMITS = [
        // tipo => [ancho recomendado, alto recomendado, mínimo, máximo]
        'kit'   => [300, 345, 100, 1200],
        'crest' => [256, 256, 64, 1024],
    ];

    /**
     * Guarda una imagen subida (PNG, JPG, GIF o WEBP). Devuelve [ruta relativa, null] o [null, error].
     * No se ejecuta ni interpreta el archivo: se comprueba que sea una imagen real y se guarda con
     * un nombre nuevo y la extensión de su tipo.
     */
    public static function storeUpload(array $file, string $what, int $teamId): array
    {
        if (!isset(self::UPLOAD_LIMITS[$what])) {
            return [null, t('Tipo de imagen no válido.')];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [null, ($file['error'] ?? 0) === UPLOAD_ERR_NO_FILE ? t('No has elegido ninguna imagen.') : t('No se pudo subir la imagen (¿es demasiado grande?).')];
        }
        $maxKb = Config::int('app', 'upload_max_kb', 1024);
        if ((int)$file['size'] > $maxKb * 1024) {
            return [null, t('La imagen ocupa demasiado (máximo {kb} KB).', ['kb' => $maxKb])];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return [null, t('No se pudo subir la imagen.')];
        }
        $info = @getimagesize($file['tmp_name']);
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) {
            return [null, t('El archivo no es una imagen PNG, JPG, GIF o WEBP.')];
        }
        [, , $min, $max] = self::UPLOAD_LIMITS[$what];
        if ($info[0] < $min || $info[1] < $min || $info[0] > $max || $info[1] > $max) {
            return [null, t('La imagen debe medir entre {min} y {max} píxeles de lado (la tuya mide {w}×{h}).', ['min' => $min, 'max' => $max, 'w' => $info[0], 'h' => $info[1]])];
        }
        $dir = APP_ROOT . '/' . self::UPLOAD_DIR . '/' . $what . 's';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return [null, t('No se puede escribir en la carpeta {dir}.', ['dir' => self::UPLOAD_DIR])];
        }
        $name = $teamId . '_' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            return [null, t('No se puede escribir en la carpeta {dir}.', ['dir' => self::UPLOAD_DIR])];
        }
        return [$what . 's/' . $name, null];
    }

    /** Borra una imagen subida (sólo dentro de la carpeta de subidas) */
    public static function deleteUpload(?string $rel): void
    {
        if (!$rel || !preg_match('#^(kits|crests)/[0-9]+_[0-9a-f]+\.(png|jpg|gif|webp)$#', $rel)) {
            return;
        }
        @unlink(APP_ROOT . '/' . self::UPLOAD_DIR . '/' . $rel);
    }
}
