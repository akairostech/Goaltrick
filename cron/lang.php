<?php
declare(strict_types=1);

/**
 * Herramienta para los archivos de idioma (lang/*.php).
 *
 *   php cron/lang.php                    Resumen: cuántos textos tiene traducidos cada idioma
 *   php cron/lang.php --scan             Busca en el código todos los textos t('...'), tx('...') y L('...')
 *                                        y actualiza lang/es.php (la lista maestra de textos)
 *   php cron/lang.php --missing en       Lista los textos que faltan por traducir en lang/en.php
 *   php cron/lang.php --sync en          Añade a lang/en.php los textos nuevos (vacíos, para traducir)
 *   php cron/lang.php --new fr Français 🇫🇷
 *                                        Crea lang/fr.php con todos los textos listos para traducir
 *
 * Un texto vacío ('') significa «sin traducir»: el juego mostrará el texto en español.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sólo desde la línea de comandos.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$cmd = $args[0] ?? '';
$dir = Lang::dir();

/** Busca los textos traducibles en el código. Devuelve [texto => archivo] */
function lang_scan(): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
    foreach (['index.php', 'install.php', 'upgrade.php', 'assets/js/app.js'] as $f) {
        if (is_file(APP_ROOT . "/$f")) {
            $files[] = APP_ROOT . "/$f";
        }
    }
    sort($files);
    $found = [];
    foreach ($files as $file) {
        $src = (string)file_get_contents($file);
        $rel = substr($file, strlen(APP_ROOT) + 1);
        $re = '/(?<![\w>$:])(?:t|tx|L|__t)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")/u';
        if (preg_match_all($re, $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $raw = $hit[1] !== '' ? $hit[1] : ($hit[2] ?? '');
                if ($raw === '') {
                    continue;
                }
                $text = $hit[1] !== '' ? str_replace(["\\'", '\\\\'], ["'", '\\'], $raw) : stripcslashes($raw);
                $found[$text] ??= $rel;
            }
        }
        // Textos marcados para traducir dentro de listas: /*t*/'Texto'
        if (preg_match_all('/\/\*t\*\/\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', $src, $m2)) {
            foreach ($m2[1] as $raw) {
                $found[str_replace(["\\'", '\\\\'], ["'", '\\'], $raw)] ??= $rel;
            }
        }
    }
    return $found;
}

function lang_write(string $code, array $meta, array $strings, array $groups = []): void
{
    $out = "<?php\n// Idioma: " . ($meta['@name'] ?? $code) . "\n"
        . "// Formato: 'texto original en español' => 'traducción'. Un valor vacío ('') = sin traducir (se muestra en español).\n"
        . "// Los {marcadores} deben mantenerse tal cual. Más información: cron/lang.php y README.md\n"
        . "return [\n";
    foreach ($meta as $k => $v) {
        $out .= '    ' . var_export($k, true) . ' => ' . var_export($v, true) . ",\n";
    }
    $last = null;
    foreach ($strings as $k => $v) {
        $g = $groups[$k] ?? null;
        if ($g !== null && $g !== $last) {
            $out .= "\n    // ---- $g\n";
            $last = $g;
        }
        $out .= '    ' . var_export((string)$k, true) . ' => ' . var_export((string)$v, true) . ",\n";
    }
    $out .= "];\n";
    file_put_contents(Lang::dir() . "/$code.php", $out);
}

function lang_split(array $data): array
{
    $meta = [];
    $strings = [];
    foreach ($data as $k => $v) {
        if (is_string($k) && str_starts_with($k, '@')) {
            $meta[$k] = $v;
        } else {
            $strings[(string)$k] = $v;
        }
    }
    return [$meta, $strings];
}

[$esMeta, $esStrings] = lang_split(Lang::file('es'));
$master = array_keys($esStrings);

switch ($cmd) {
    case '--scan':
        $found = lang_scan();
        $all = $found;
        foreach ($esStrings as $k => $_) {
            $all[$k] ??= 'otros';
        }
        asort($all);
        $strings = [];
        foreach ($all as $k => $_) {
            $strings[$k] = $k;
        }
        lang_write('es', $esMeta + ['@name' => 'Español', '@flag' => '🇪🇸', '@locale' => 'es'], $strings, $all);
        $new = count(array_diff_key($found, $esStrings));
        echo 'lang/es.php actualizado: ' . count($strings) . " textos ($new nuevos).\n";
        break;

    case '--missing':
    case '--sync':
        $code = $args[1] ?? '';
        if (!is_file("$dir/$code.php")) {
            exit("No existe lang/$code.php\n");
        }
        [$meta, $strings] = lang_split(Lang::file($code));
        $missing = array_values(array_filter($master, fn($k) => !isset($strings[$k]) || $strings[$k] === ''));
        if ($cmd === '--missing') {
            foreach ($missing as $k) {
                echo $k, "\n";
            }
            echo count($missing) . " textos sin traducir en lang/$code.php\n";
            break;
        }
        $merged = [];
        foreach ($master as $k) {
            $merged[$k] = $strings[$k] ?? '';
        }
        foreach ($strings as $k => $v) {
            $merged[$k] ??= $v; // conserva traducciones de textos que ya no se usan
        }
        $groups = [];
        lang_write($code, $meta, $merged, $groups);
        echo "lang/$code.php sincronizado: " . count($missing) . " textos pendientes de traducir.\n";
        break;

    case '--new':
        $code = $args[1] ?? '';
        if (!preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $code)) {
            exit("Uso: php cron/lang.php --new fr Français 🇫🇷\n");
        }
        if (is_file("$dir/$code.php")) {
            exit("lang/$code.php ya existe. Usa --sync $code\n");
        }
        $strings = array_fill_keys($master, '');
        lang_write($code, ['@name' => $args[2] ?? strtoupper($code), '@flag' => $args[3] ?? '🏳', '@locale' => $code], $strings);
        echo "Creado lang/$code.php con " . count($strings) . " textos para traducir.\n";
        break;

    default:
        echo count($master) . " textos en la lista maestra (lang/es.php)\n";
        foreach (Lang::available() as $code => [$name, $flag]) {
            [, $strings] = lang_split(Lang::file($code));
            $done = count(array_filter($master, fn($k) => isset($strings[$k]) && $strings[$k] !== ''));
            printf("  %-6s %-14s %5d / %d traducidos (%d%%)\n", $code, $name, $done, count($master), count($master) ? round($done / count($master) * 100) : 100);
        }
        echo "Opciones: --scan | --missing <código> | --sync <código> | --new <código> <nombre> <bandera>\n";
}
