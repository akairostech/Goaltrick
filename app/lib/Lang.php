<?php
declare(strict_types=1);

/**
 * Sistema de idiomas.
 *
 * Los textos del juego se escriben en español dentro del código y se pasan por t():
 *     t('Mi club')                                 -> «My club» en inglés
 *     t('Hola, {name}', ['name' => $user])         -> los {marcadores} se sustituyen
 *
 * Cada idioma es un archivo lang/<código>.php que devuelve un array «texto en español» => «traducción».
 * Las claves que empiezan por @ son datos del idioma (@name, @flag, @locale).
 * Si un mismo texto español tiene dos significados se le añade un contexto: 'Lateral##posición'
 * (en español se muestra sólo «Lateral»; los demás idiomas pueden traducir cada uno por separado).
 * Si falta una traducción se muestra el texto en español, así que nunca se rompe nada.
 *
 * Para añadir un idioma: copia lang/en.php (o lang/es.php) como lang/fr.php, cambia @name/@flag y
 * traduce los textos de la derecha. Aparecerá solo en el selector de idioma.
 * Herramienta de ayuda: php cron/lang.php (lista textos que faltan, crea plantillas...).
 *
 * Los textos que se guardan en la base de datos (noticias, crónicas, movimientos...) se guardan
 * con tx(), que almacena la plantilla y sus datos; tdb() los muestra en el idioma de quien los lee.
 */
final class Lang
{
    public const COOKIE = 'gt_lang';
    /** Marca de los textos guardados con tx() */
    public const MARK = "\u{00A7}";

    private static ?string $code = null;
    private static array $files = [];
    private static ?array $available = null;

    public static function dir(): string
    {
        return APP_ROOT . '/lang';
    }

    /** Idiomas disponibles: código => [nombre, bandera] */
    public static function available(): array
    {
        if (self::$available === null) {
            self::$available = [];
            foreach (glob(self::dir() . '/*.php') ?: [] as $file) {
                $code = basename($file, '.php');
                if (!preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $code)) {
                    continue;
                }
                $data = self::file($code);
                self::$available[$code] = [(string)($data['@name'] ?? strtoupper($code)), (string)($data['@flag'] ?? '🏳')];
            }
            uksort(self::$available, fn($a, $b) => ($a === 'es' ? -1 : ($b === 'es' ? 1 : strcmp($a, $b))));
        }
        return self::$available;
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && $code !== '' && isset(self::available()[$code]);
    }

    public static function file(string $code): array
    {
        if (!isset(self::$files[$code])) {
            $path = self::dir() . "/$code.php";
            $data = is_file($path) ? include $path : [];
            self::$files[$code] = is_array($data) ? $data : [];
        }
        return self::$files[$code];
    }

    public static function default(): string
    {
        $c = (string)Config::get('app', 'language', 'es');
        return self::isValid($c) ? $c : 'es';
    }

    /** Idioma de la petición actual */
    public static function code(): string
    {
        if (self::$code === null) {
            self::$code = self::detect();
        }
        return self::$code;
    }

    public static function set(string $code): void
    {
        if (self::isValid($code)) {
            self::$code = $code;
        }
    }

    private static function detect(): string
    {
        if (PHP_SAPI === 'cli') {
            return self::default();
        }
        try {
            $u = Auth::user();
            if ($u && self::isValid($u['language'] ?? null)) {
                return $u['language'];
            }
        } catch (Throwable $e) {
            // Base de datos aún sin instalar o sin la columna: se ignora
        }
        $c = $_COOKIE[self::COOKIE] ?? null;
        if (self::isValid($c)) {
            return $c;
        }
        // Primera visita: idioma del navegador si lo tenemos
        foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $l = strtolower(substr(trim($part), 0, 2));
            if (self::isValid($l)) {
                return Config::bool('app', 'detect_browser_language', true) ? $l : self::default();
            }
        }
        return self::default();
    }

    /** Cambia el idioma del visitante (cookie + preferencia del usuario) */
    public static function choose(string $code): bool
    {
        if (!self::isValid($code)) {
            return false;
        }
        self::$code = $code;
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, $code, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
        }
        try {
            $u = Auth::user();
            if ($u) {
                DB::exec('UPDATE users SET language = ? WHERE id = ?', [$code, $u['id']]);
            }
        } catch (Throwable $e) {
        }
        return true;
    }

    /** Traduce un texto (escrito en español) al idioma actual y sustituye los {marcadores} */
    public static function tr(string $text, array $args = [], ?string $code = null): string
    {
        $code = $code ?? self::code();
        if ($code !== 'es') {
            $text = self::lookup($text, $code);
        }
        // Contexto para textos iguales con distinto significado: t('Lateral##posición') muestra «Lateral»
        if (($p = strpos($text, '##')) !== false) {
            $text = substr($text, 0, $p);
        }
        if (!$args) {
            return $text;
        }
        $rep = [];
        foreach ($args as $k => $v) {
            $rep['{' . $k . '}'] = is_string($v) ? self::stored($v, $code) : (string)$v;
        }
        return strtr($text, $rep);
    }

    private static function lookup(string $text, string $code): string
    {
        $tr = self::file($code)[$text] ?? null;
        if (is_string($tr) && $tr !== '') {
            return $tr;
        }
        // Idiomas regionales (pt_BR -> pt)
        if (strlen($code) > 2) {
            return self::lookup($text, substr($code, 0, 2));
        }
        return $text;
    }

    /** Codifica un texto para guardarlo en la base de datos y traducirlo al mostrarlo */
    public static function encode(string $text, array $args = []): string
    {
        $data = ['k' => $text];
        if ($args) {
            $data['a'] = array_map(fn($v) => is_scalar($v) || $v === null ? (string)$v : json_encode($v), $args);
        }
        return self::MARK . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** Une varios textos codificados en uno (se muestran separados por un espacio) */
    public static function join(string ...$parts): string
    {
        $list = [];
        foreach ($parts as $p) {
            $d = self::decode($p);
            if ($d === null) {
                $list[] = ['k' => $p, 'raw' => 1];
            } elseif (array_is_list($d)) {
                array_push($list, ...$d);
            } else {
                $list[] = $d;
            }
        }
        return self::MARK . json_encode($list, JSON_UNESCAPED_UNICODE);
    }

    private static function decode(string $s): ?array
    {
        if (!str_starts_with($s, self::MARK)) {
            return null;
        }
        $d = json_decode(substr($s, strlen(self::MARK)), true);
        return is_array($d) ? $d : null;
    }

    /** Muestra un texto guardado (codificado con tx() o texto plano antiguo) */
    public static function stored(?string $s, ?string $code = null): string
    {
        if ($s === null || $s === '') {
            return (string)$s;
        }
        $d = self::decode($s);
        if ($d === null) {
            return $s;
        }
        $one = function (array $p) use ($code): string {
            if (!empty($p['raw'])) {
                return (string)$p['k'];
            }
            return self::tr((string)($p['k'] ?? ''), (array)($p['a'] ?? []), $code);
        };
        if (array_is_list($d)) {
            return implode(' ', array_map($one, $d));
        }
        return $one($d);
    }

    /** Diccionario para JavaScript (textos usados con L() en assets/js/app.js) */
    public static function jsDictionary(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::tr($k);
        }
        return $out;
    }
}
