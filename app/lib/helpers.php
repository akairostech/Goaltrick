<?php
declare(strict_types=1);

/* =====================================================================
 *  Funciones de ayuda generales
 * ===================================================================== */

/* ----------------------------- Idiomas ------------------------------ */

/** Traduce un texto escrito en español al idioma del usuario. t('Hola, {name}', ['name' => 'Ana']) */
function t(string $text, array $args = []): string
{
    return Lang::tr($text, $args);
}

/** Igual que t() pero para textos que se GUARDAN en la base de datos: se traducen al mostrarlos con tdb() */
function tx(string $text, array $args = []): string
{
    return Lang::encode($text, $args);
}

/** Une varios textos de tx() en uno solo */
function tx_join(string ...$parts): string
{
    return Lang::join(...$parts);
}

/** Muestra (traducido) un texto guardado en la base de datos */
function tdb(?string $stored): string
{
    return Lang::stored($stored);
}

/** URL de la página actual cambiando el idioma */
function lang_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    return 'index.php?' . http_build_query($q);
}

function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function money(int|float|string|null $amount, bool $sign = false): string
{
    $amount = (float)$amount;
    [$dp, $tp] = num_separators();
    $txt = number_format(abs($amount), 0, $dp, $tp);
    $cur = (string)Config::get('app', 'currency', '€');
    $prefix = $amount < 0 ? '-' : ($sign && $amount > 0 ? '+' : '');
    return $prefix . $txt . ' ' . $cur;
}

function num(int|float|string|null $n, int $dec = 0): string
{
    [$dp, $tp] = num_separators();
    return number_format((float)$n, $dec, $dp, $tp);
}

/** Separadores decimal y de miles del idioma actual (se pueden definir en lang/xx.php con @decimal y @thousands) */
function num_separators(): array
{
    static $cache = [];
    $code = Lang::code();
    if (!isset($cache[$code])) {
        $f = Lang::file($code);
        $cache[$code] = [(string)($f['@decimal'] ?? ','), (string)($f['@thousands'] ?? '.')];
    }
    return $cache[$code];
}

function base_url(): string
{
    $cfg = trim((string)Config::get('app', 'base_url', ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/');
    }
    if (PHP_SAPI === 'cli') {
        return '';
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/');
}

function url(string $page = 'home', array $params = []): string
{
    $q = array_merge(['page' => $page], $params);
    if ($page === 'home' && !$params) {
        return base_url() . '/index.php';
    }
    return base_url() . '/index.php?' . http_build_query($q);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return base_url() . '/assets/' . $path . '?v=' . $v;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ------------------------------ CSRF -------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        flash('error', t('La sesión del formulario ha caducado. Inténtalo de nuevo.'));
        redirect($_SERVER['HTTP_REFERER'] ?? url());
    }
}

/* -------------------------- Usuario actual -------------------------- */

function current_user(): ?array
{
    return Auth::user();
}

function current_team(): ?array
{
    static $team = false;
    if ($team === false) {
        $u = current_user();
        $team = $u ? DB::row('SELECT * FROM teams WHERE user_id = ?', [$u['id']]) : null;
    }
    return $team;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', t('Debes iniciar sesión para ver esta página.'));
        redirect(url('login'));
    }
    return $u;
}

function require_team(): array
{
    require_login();
    $t = current_team();
    if (!$t) {
        flash('error', t('Tu usuario no tiene un equipo asignado.'));
        redirect(url('home'));
    }
    return $t;
}

function require_admin(): array
{
    $u = require_login();
    if (!(int)$u['is_admin']) {
        http_response_code(403);
        flash('error', t('Acceso restringido a administradores.'));
        redirect(url('home'));
    }
    return $u;
}

/* ---------------------------- Fechas -------------------------------- */

function weekday_name(int $d): string
{
    return t([1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'][$d] ?? '');
}

function fdate(?string $dt, bool $time = true): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    return date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function fdate_long(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    return ucfirst(weekday_name((int)date('N', $ts))) . ' ' . date('d/m H:i', $ts);
}

function time_left(string $dt): string
{
    $diff = strtotime($dt) - time();
    if ($diff <= 0) {
        return t('finalizado');
    }
    $d = intdiv($diff, 86400);
    $h = intdiv($diff % 86400, 3600);
    $m = intdiv($diff % 3600, 60);
    if ($d > 0) {
        return "{$d}d {$h}h";
    }
    if ($h > 0) {
        return "{$h}h {$m}m";
    }
    return "{$m}m";
}

/* ------------------------- Textos del juego -------------------------- */

const SKILL_LEVELS = [
    'inexistente', 'desastroso', 'horrible', 'pobre', 'débil', 'insuficiente',
    'aceptable', 'bueno', 'excelente', 'formidable', 'destacado', 'brillante',
    'magnífico', 'clase mundial', 'sobrenatural', 'titánico', 'extraterrestre',
    'mítico', 'mágico', 'utópico', 'divino',
];

const SKILLS = [
    'keeper'     => 'Portería',
    'defending'  => 'Defensa',
    'playmaking' => 'Jugadas',
    'winger'     => 'Lateral',
    'passing'    => 'Pases',
    'scoring'    => 'Anotación',
    'set_pieces' => 'Balón parado',
];

const SPECIALTIES = [
    'technical'     => ['Técnico', '🎯'],
    'quick'         => ['Rápido', '⚡'],
    'powerful'      => ['Potente', '💪'],
    'unpredictable' => ['Imprevisible', '🎲'],
    'head'          => ['Cabeceador', '🗣'],
    'resilient'     => ['Resistente', '🛡'],
];

const ROLES = [
    'GK' => 'Portero',
    'WB' => 'Lateral##posición',
    'CD' => 'Defensa central',
    'W'  => 'Extremo',
    'IM' => 'Mediocampista',
    'FW' => 'Delantero',
];

const TACTICS = [
    'normal'     => 'Normal',
    'pressing'   => 'Presión',
    'counter'    => 'Contraataque',
    'aim'        => 'Ataque por el centro',
    'aow'        => 'Ataque por las bandas',
    'longshots'  => 'Tiros lejanos',
];

const ATTITUDES = [
    'pic'    => 'Partido tranquilo (PIC)',
    'normal' => 'Normal',
    'mots'   => 'Partido del siglo (MOTS)',
];

const TRAINING_TYPES = [
    'keeper'     => 'Portería',
    'defending'  => 'Defensa',
    'playmaking' => 'Jugadas',
    'winger'     => 'Lateral',
    'passing'    => 'Pases',
    'scoring'    => 'Anotación',
    'set_pieces' => 'Balón parado',
];

/** Nombre traducido de una posición natural (GK, CD, WB...) */
function role_name(?string $code): string
{
    return isset(ROLES[$code ?? '']) ? t(ROLES[$code]) : (string)$code;
}

/** Nombre traducido de una habilidad (keeper, defending...) */
function skill_title(string $key): string
{
    return isset(SKILLS[$key]) ? t(SKILLS[$key]) : (TRAINING_TYPES[$key] ?? $key);
}

/** Nombre traducido de una especialidad */
function specialty_name(?string $s): string
{
    return isset(SPECIALTIES[$s ?? '']) ? t(SPECIALTIES[$s][0]) : '';
}

function skill_name(float|int|string|null $v): string
{
    $i = max(0, min(20, (int)floor((float)$v)));
    return t(SKILL_LEVELS[$i]);
}

function skill_label(float|int|string|null $v): string
{
    $i = max(0, min(20, (int)floor((float)$v)));
    return t(SKILL_LEVELS[$i]) . ' (' . $i . ')';
}

function skill_bar(float|int|string|null $v, int $max = 20): string
{
    $f = (float)$v;
    $pct = max(0, min(100, $f / $max * 100));
    $lvl = (int)floor($f);
    $cls = $lvl >= 13 ? 'sb-top' : ($lvl >= 9 ? 'sb-high' : ($lvl >= 6 ? 'sb-mid' : 'sb-low'));
    return '<span class="skillbar ' . $cls . '" title="' . e(skill_label($f)) . '"><span style="width:' . round($pct, 1) . '%"></span></span>';
}

function form_name(float|int|string|null $v): string
{
    $i = max(1, min(8, (int)floor((float)$v)));
    return t(SKILL_LEVELS[$i]);
}

function mood_name(float|int|string|null $v, string $kind = 'spirit'): string
{
    $i = max(0, min(10, (int)round((float)$v)));
    $lists = [
        'spirit' => ['como en la guerra', 'furioso', 'irritado', 'tranquilo', 'compuesto', 'satisfecho',
                     'contento', 'encantado', 'eufórico', 'en el paraíso', 'por las nubes'],
        'confidence' => ['inexistente', 'desastrosa', 'pobre', 'decente', 'fuerte', 'muy fuerte',
                         'algo exagerada', 'exagerada', 'arrogante', 'ególatra', 'completamente exagerada'],
        'fans' => ['asesinos', 'furiosos', 'enfadados', 'irritados', 'decepcionados', 'tranquilos',
                   'contentos', 'satisfechos', 'encantados', 'enamorados', 'en éxtasis'],
    ];
    return t($lists[$kind][$i] ?? '');
}

function specialty_badge(?string $s): string
{
    if (!$s || !isset(SPECIALTIES[$s])) {
        return '';
    }
    [$name, $icon] = SPECIALTIES[$s];
    $name = t($name);
    return '<span class="spec" title="' . e($name) . '">' . $icon . ' ' . e($name) . '</span>';
}

/** v1.10.9: datos de un jugador para el tooltip (crónicas); misma forma que los de la página de alineación */
function player_tip_data(array $p): array
{
    $skills = [];
    foreach (SKILLS as $k => $label) {
        $skills[] = [skill_title($k), (int)floor((float)$p[$k]), skill_name($p[$k])];
    }
    return [
        'id'       => (int)$p['id'],
        'name'     => player_name($p),
        'img'      => avatar_url($p),
        'role'     => $p['role'],
        'roleName' => role_name($p['role']),
        'age'      => (int)$p['age_years'],
        'days'     => (int)$p['age_days'],
        'tsi'      => num($p['tsi']),
        'formName' => form_name($p['form']),
        'stamina'  => (int)floor((float)$p['stamina']),
        'xp'       => (int)floor((float)$p['experience']),
        'lead'     => (int)$p['leadership'],
        'spec'     => $p['specialty'] && isset(SPECIALTIES[$p['specialty']]) ? SPECIALTIES[$p['specialty']][1] . ' ' . specialty_name($p['specialty']) : '',
        'inj'      => (float)$p['injury_weeks'],
        'susp'     => (int)$p['suspended'],
        'yc'       => (int)$p['yellow_cards'],
        'goals'    => (int)$p['goals_season'],
        'skills'   => $skills,
    ];
}

function player_name(array $p): string
{
    return $p['first_name'] . ' ' . $p['last_name'];
}

function player_link(array $p): string
{
    return '<a href="' . e(url('player', ['id' => $p['id']])) . '">' . e(player_name($p)) . '</a>';
}

function team_link(int|string $id, string $name): string
{
    return '<a href="' . e(url('team', ['id' => $id])) . '">' . e($name) . '</a>';
}

function age_label(array $p): string
{
    return t('{y} años y {d} días', ['y' => $p['age_years'], 'd' => $p['age_days']]);
}

function stars(float|int|string|null $s): string
{
    $s = (float)$s;
    if ($s <= 0) {
        return '<span class="muted">-</span>';
    }
    $full = (int)floor($s);
    $half = ($s - $full) >= 0.5;
    // v1.10.6: cada grupo de 5 estrellas se muestra como una estrella celeste brillante
    $big = $full > 5 ? intdiv($full, 5) : 0;
    $rest = $full - $big * 5;
    return '<span class="stars" title="' . e(t('{n} estrellas', ['n' => num($s, 1)])) . '">'
        . str_repeat('<span class="star-5" aria-hidden="true">★</span>', $big) . str_repeat('★', $rest) . ($half ? '½' : '') . '</span>';
}

/** Nombre de la valoración de un sector (estilo "débil (bajo)") */
function rating_name(float $r): string
{
    // $r está en escala 1..20 aproximada (ej: 7.25 = bueno alto)
    $r = max(0.0, $r);
    $lvl = (int)floor($r);
    $sub = $r - $lvl;
    $subs = ['muy bajo', 'bajo', 'alto', 'muy alto'];
    $s = $subs[min(3, (int)floor($sub * 4))];
    return t(SKILL_LEVELS[min(20, $lvl)]) . ' (' . t($s) . ')';
}

function paginate(int $total, int $perPage, int $current, string $page, array $params = []): string
{
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $current) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $html .= '<a href="' . e(url($page, array_merge($params, ['p' => $i]))) . '">' . $i . '</a>';
        }
    }
    return $html . '</div>';
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function rnd(float $min = 0.0, float $max = 1.0): float
{
    return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
}

/** Número aleatorio con distribución aproximadamente normal */
function gauss(float $mean = 0.0, float $sd = 1.0): float
{
    $u = max(1e-9, rnd());
    $v = rnd();
    return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
}

function clamp(float $v, float $min, float $max): float
{
    return max($min, min($max, $v));
}

/** Etiqueta del tipo de partido: "Liga · Jornada 3", "Amistoso", "Mundial · Final"... */
function match_type_label(array $m): string
{
    return match ($m['type']) {
        'league'   => t('Liga · Jornada {n}', ['n' => (int)$m['round']]),
        'worldcup' => t('Mundial') . ' · ' . WorldCupService::stageName($m['cup_stage'] ?? ''),
        'cup'      => t('Copa') . ' · ' . CupService::stageName($m['cup_stage'] ?? ''),
        default    => t('Amistoso'),
    };
}

/** Resultado con penaltis si los hubo */
function score_label(array $m): string
{
    $t = (int)$m['home_goals'] . ' - ' . (int)$m['away_goals'];
    if (isset($m['home_pens']) && $m['home_pens'] !== null) {
        $t .= ' (' . (int)$m['home_pens'] . '-' . (int)$m['away_pens'] . ' ' . t('pen.') . ')';
    }
    return $t;
}

function flag(?string $code, ?string $title = null): string
{
    return $code ? NationService::flagImg($code, $title ?? strtoupper($code)) : '';
}

function nation_link(int|string $countryId, string $name, ?string $code = null): string
{
    return ($code ? flag($code) . ' ' : '') . '<a href="' . e(url('nation', ['id' => $countryId])) . '">' . e(t($name)) . '</a>';
}

/* --------------------------- Imágenes --------------------------------- */

const AVATAR_COUNT = 5000;

/** Número de uniforme (1..1000) de cada club, cargado una sola vez por petición */
function team_kit_n(int $teamId): ?int
{
    static $kits = null;
    if ($kits === null) {
        $kits = [];
        try {
            foreach (DB::all('SELECT id, kit_id FROM teams') as $r) {
                $kits[(int)$r['id']] = (int)$r['kit_id'];
            }
        } catch (Throwable $e) {
            // Base de datos sin actualizar todavía
        }
    }
    if (!array_key_exists($teamId, $kits)) {
        return null;
    }
    return team_art_n(['id' => $teamId, 'kit_id' => $kits[$teamId]], 'kit');
}

/**
 * Cara del jugador. Si el jugador está en un club, viste la camiseta del uniforme que ha elegido
 * ese club (face.php); $kit permite forzar un uniforme concreto.
 */
function avatar_url(array $p, ?int $kit = null): string
{
    $n = (int)($p['avatar'] ?? 0);
    if ($n < 1 || $n > AVATAR_COUNT) {
        $n = ((int)($p['id'] ?? 0) * 7) % AVATAR_COUNT + 1;
    }
    $kit = $kit ?? (!empty($p['team_id']) ? team_kit_n((int)$p['team_id']) : null);
    if ($kit) {
        return base_url() . '/face.php?a=' . $n . '&k=' . $kit . '&v=' . APP_VERSION;
    }
    return asset(sprintf('img/avatars/%03d.svg', $n));
}

function avatar_img(array $p, int $size = 24, string $class = 'avatar'): string
{
    return '<img class="' . e($class) . '" src="' . e(avatar_url($p)) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy">';
}

function trophy_url(int $level): string
{
    return asset('img/trophies/trophy-' . max(1, min(3, $level)) . '.svg');
}

/** Vitrina de trofeos de un club (títulos de liga) */
function trophy_cabinet(int $teamId, bool $own = false): string
{
    $titles = DB::all(
        'SELECT sh.season, sh.points, sh.goals_for, sh.goals_against, lu.level, lu.name AS unit, c.name AS country, c.code
         FROM season_history sh JOIN league_units lu ON lu.id = sh.league_unit_id JOIN countries c ON c.id = lu.country_id
         WHERE sh.team_id = ? AND sh.position = 1 ORDER BY lu.level, sh.season DESC',
        [$teamId]
    );
    $cups = DB::all(
        'SELECT lc.season, c.name AS country, c.code FROM league_cups lc JOIN countries c ON c.id = lc.country_id
         WHERE lc.winner_team_id = ? ORDER BY lc.season DESC',
        [$teamId]
    );
    $count = count($titles) + count($cups);
    $h = '<div class="box cabinet"><div class="box-head">🏆 ' . e(t('Vitrina de trofeos')) . ' <span class="small">' . e(t('{n} título(s)', ['n' => $count])) . '</span></div>';
    $h .= '<div class="cabinet-inner"><div class="shelf">';
    foreach ($cups as $c) {
        $h .= '<div class="trophy trophy-cup" title="' . e(t('Copa de {country}', ['country' => t($c['country'])])) . '">'
            . '<img src="' . e(asset('img/trophies/trophy-cup.svg')) . '" alt="">'
            . '<div class="trophy-label"><strong>' . e(t('Campeón de la Copa')) . '</strong><br>'
            . NationService::flagImg($c['code']) . ' ' . e(t($c['country'])) . ' · ' . e(t('Temp. {n}', ['n' => (int)$c['season']])) . '</div></div>';
    }
    if (!$count) {
        foreach ([1, 2, 3] as $lvl) {
            $h .= '<div class="trophy empty" title="' . e(t('Aún sin conquistar')) . '"><img src="' . e(trophy_url($lvl)) . '" alt=""><div class="trophy-label">' . e(t('Campeón de División {n}', ['n' => LeagueService::roman($lvl)])) . '</div></div>';
        }
    } else {
        foreach ($titles as $t) {
            $h .= '<div class="trophy" title="' . e(t('{p} puntos · {gf}:{ga} goles', ['p' => $t['points'], 'gf' => $t['goals_for'], 'ga' => $t['goals_against']])) . '">'
                . '<img src="' . e(trophy_url((int)$t['level'])) . '" alt="">'
                . '<div class="trophy-label"><strong>' . e(t('Campeón División {n}', ['n' => LeagueService::roman((int)$t['level'])])) . '</strong><br>'
                . NationService::flagImg($t['code']) . ' ' . e(t('División {name}', ['name' => $t['unit']])) . ' · ' . e(t('Temp. {n}', ['n' => (int)$t['season']])) . '</div></div>';
        }
    }
    if (!$count) {
        $h .= '<div class="trophy empty" title="' . e(t('Aún sin conquistar')) . '"><img src="' . e(asset('img/trophies/trophy-cup.svg')) . '" alt=""><div class="trophy-label">' . e(t('Campeón de Copa')) . '</div></div>';
    }
    $h .= '</div></div>';
    if (!$count) {
        $h .= '<div class="box-body small muted center">' . e($own ? t('Tu vitrina está vacía. ¡Gana tu división para conseguir el primer trofeo!') : t('Este club todavía no ha ganado ningún título.')) . '</div>';
    }
    return $h . '</div>';
}

/** Caras de presidente para el perfil público del mánager (assets/img/presidents/0001-1000.svg), 125 por tono de piel */
const PRESIDENT_COUNT = 1000;
const PRESIDENT_PER_SKIN = 125;
const PRESIDENT_SKINS = [
    ['Porcelana', '#ffe3cf'], ['Marfil', '#f9d7bd'], ['Miel', '#f1c27d'], ['Arena', '#e0ac69'],
    ['Canela', '#c68642'], ['Avellana', '#a0673b'], ['Cacao', '#7a4a2a'], ['Ébano', '#5a3520'],
];

/** Número de cara de un usuario: la elegida o, si no eligió ninguna, una fija según su id */
function president_n(array $u): int
{
    $n = (int)($u['avatar'] ?? 0);
    if ($n >= 1 && $n <= PRESIDENT_COUNT) {
        return $n;
    }
    return ((int)($u['id'] ?? $u['uid'] ?? 0) * 389) % PRESIDENT_COUNT + 1;
}

function president_url(int $n): string
{
    return asset(sprintf('img/presidents/%04d.svg', max(1, min(PRESIDENT_COUNT, $n))));
}

/** Tono de piel (0..7) de una cara de presidente */
function president_skin(int $n): int
{
    return intdiv(max(1, $n) - 1, PRESIDENT_PER_SKIN);
}

function president_img(array $u, int $size = 32, string $class = 'avatar president'): string
{
    return '<img class="' . e($class) . '" src="' . e(president_url(president_n($u))) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy">';
}

/** Enlace al perfil público de un mánager */
function manager_link(int|string|null $uid, ?string $name, bool $face = false): string
{
    if (!$uid || $name === null || $name === '') {
        return '<span class="muted">—</span>';
    }
    $img = $face ? president_img(['id' => (int)$uid, 'avatar' => manager_avatar((int)$uid)], 20) . ' ' : '';
    return '<a class="manager-link" href="' . e(url('manager', ['id' => $uid])) . '">' . $img . e($name) . '</a>';
}

/** Cara elegida por cada mánager, cargada una sola vez por petición */
function manager_avatar(int $uid): int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (DB::all('SELECT id, avatar FROM users WHERE avatar > 0') as $r) {
            $map[(int)$r['id']] = (int)$r['avatar'];
        }
    }
    return $map[$uid] ?? 0;
}

/** Textos que usa assets/js/app.js (con L('...')); se envían traducidos a la página */
const JS_STRINGS = [
    'Alineación cargada. Revísala y pulsa «Enviar» para usarla en el próximo partido.',
    'Condición',
    'En tu plantilla hay jugadores que rinden bastante más en esta posición',
    'Especialidad:',
    'Exp.',
    'Forma',
    'F{f} · C{c}',
    'Goles',
    'Lesionado {n} sem.',
    'Lesionado: {n} semana(s)',
    'Liderazgo',
    'Magullado',
    'Mejor posición:',
    'No hay jugadores en este filtro.',
    'Quitar',
    'Sancionado',
    'Sancionado para el próximo partido',
    'Tarjetas amarillas: {n}',
    'Ya tienes 11 titulares. Suelta al jugador sobre un titular para sustituirlo o quita antes a otro.',
    'aquí rinde al {pct}% del mejor de tu plantilla',
    'finalizado',
    'sin portero',
    '{age} años · TSI {tsi} · forma {form}',
    '{n} amarillas',
    '{y} años y {d} días',
];

/* ------------------------- Uniformes y escudos -------------------------- */

/** Número de diseño (1..1000) del uniforme o escudo de un club */
function team_art_n(array $t, string $what): int
{
    $n = (int)($t[$what . '_id'] ?? 0);
    return $n >= 1 && $n <= ClubArt::COUNT ? $n : ClubArt::defaultFor((int)($t['id'] ?? 0), $what);
}

/** URL de la imagen del uniforme ('kit') o del escudo ('crest') de un club */
function team_art_url(array $t, string $what): string
{
    $custom = $t[$what . '_custom'] ?? null;
    if ($custom) {
        return asset('uploads/' . $custom);
    }
    return base_url() . '/art.php?k=' . $what . '&n=' . team_art_n($t, $what) . '&v=' . APP_VERSION;
}

function art_url(string $what, int $n): string
{
    return base_url() . '/art.php?k=' . $what . '&n=' . $n . '&v=' . APP_VERSION;
}

/** <img> con el escudo de un club */
function crest_img(array $t, int $size = 24, string $class = 'crest'): string
{
    return '<img class="' . e($class) . '" src="' . e(team_art_url($t, 'crest')) . '" width="' . $size . '" height="' . round($size * 1.15) . '" alt="" loading="lazy">';
}

/** <img> con el uniforme de un club */
function kit_img(array $t, int $size = 80, string $class = 'kit'): string
{
    return '<img class="' . e($class) . '" src="' . e(team_art_url($t, 'kit')) . '" width="' . $size . '" height="' . round($size * 1.15) . '" alt="" loading="lazy">';
}

/** Pestañas de navegación de la administración (se muestran en todas sus páginas) */
function admin_tabs(string $active): string
{
    $open = Moderation::openTicketsCount();
    $tabs = [
        'game'    => [url('admin'), e(t('Juego'))],
        'users'   => [url('admin', ['tab' => 'users']), e(t('Usuarios'))],
        'news'    => [url('admin', ['tab' => 'news']), e(t('Anuncios'))],
        'support' => [url('admin_tickets'), '🆘 ' . e(t('Soporte')) . ($open ? ' <span class="badge">' . $open . '</span>' : '')],
        'teams'   => [url('admin_team'), e(t('Editar equipos'))],
        'config'  => [url('admin', ['tab' => 'config']), e(t('Configuración'))],
        'reset'   => [url('admin_reset'), '<span style="color:#b0281b">' . e(t('Reiniciar partida')) . '</span>'],
    ];
    $h = '<div class="tabs admin-tabs">';
    foreach ($tabs as $k => [$href, $label]) {
        $h .= '<a class="' . ($k === $active ? 'active' : '') . '" href="' . e($href) . '">' . $label . '</a>';
    }
    return $h . '</div>';
}

/** Logotipo del juego: balón caricaturesco + nombre en dos colores ("Goal" + "Trick") */
function logo_html(string $site): string
{
    // Se parte el nombre en la segunda mayúscula (GoalTrick -> Goal|Trick); si no hay, va entero
    $a = $site;
    $b = '';
    if (preg_match('/^(.+?)(\p{Lu}.*)$/u', mb_substr($site, 1), $m)) {
        $a = mb_substr($site, 0, 1) . $m[1];
        $b = $m[2];
    }
    return '<img class="logo-ball" src="' . e(asset('img/logo-ball.svg')) . '" width="54" height="48" alt="">'
        . '<span class="logo-text"><span class="lt-a">' . e($a) . '</span>' . ($b !== '' ? '<span class="lt-b">' . e($b) . '</span>' : '') . '</span>';
}
