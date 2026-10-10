<?php
declare(strict_types=1);

/* =====================================================================
 *  Funciones de ayuda generales
 * ===================================================================== */

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
    $txt = number_format(abs($amount), 0, ',', '.');
    $cur = (string)Config::get('app', 'currency', '€');
    $prefix = $amount < 0 ? '-' : ($sign && $amount > 0 ? '+' : '');
    return $prefix . $txt . ' ' . $cur;
}

function num(int|float|string|null $n, int $dec = 0): string
{
    return number_format((float)$n, $dec, ',', '.');
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
        flash('error', 'La sesión del formulario ha caducado. Inténtalo de nuevo.');
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
        flash('info', 'Debes iniciar sesión para ver esta página.');
        redirect(url('login'));
    }
    return $u;
}

function require_team(): array
{
    require_login();
    $t = current_team();
    if (!$t) {
        flash('error', 'Tu usuario no tiene un equipo asignado.');
        redirect(url('home'));
    }
    return $t;
}

function require_admin(): array
{
    $u = require_login();
    if (!(int)$u['is_admin']) {
        http_response_code(403);
        flash('error', 'Acceso restringido a administradores.');
        redirect(url('home'));
    }
    return $u;
}

/* ---------------------------- Fechas -------------------------------- */

function weekday_name(int $d): string
{
    return [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'][$d] ?? '';
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
        return 'finalizado';
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
    'WB' => 'Lateral',
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

function skill_name(float|int|string|null $v): string
{
    $i = max(0, min(20, (int)floor((float)$v)));
    return SKILL_LEVELS[$i];
}

function skill_label(float|int|string|null $v): string
{
    $i = max(0, min(20, (int)floor((float)$v)));
    return SKILL_LEVELS[$i] . ' (' . $i . ')';
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
    return SKILL_LEVELS[$i];
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
    return $lists[$kind][$i] ?? '';
}

function specialty_badge(?string $s): string
{
    if (!$s || !isset(SPECIALTIES[$s])) {
        return '';
    }
    [$name, $icon] = SPECIALTIES[$s];
    return '<span class="spec" title="' . e($name) . '">' . $icon . ' ' . e($name) . '</span>';
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
    return $p['age_years'] . ' años y ' . $p['age_days'] . ' días';
}

function stars(float|int|string|null $s): string
{
    $s = (float)$s;
    if ($s <= 0) {
        return '<span class="muted">-</span>';
    }
    $full = (int)floor($s);
    $half = ($s - $full) >= 0.5;
    return '<span class="stars" title="' . num($s, 1) . ' estrellas">' . str_repeat('★', $full) . ($half ? '½' : '') . '</span>';
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
    return SKILL_LEVELS[min(20, $lvl)] . ' (' . $s . ')';
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
        'league'   => 'Liga · Jornada ' . (int)$m['round'],
        'worldcup' => 'Mundial · ' . WorldCupService::stageName($m['cup_stage'] ?? ''),
        'cup'      => 'Copa · ' . CupService::stageName($m['cup_stage'] ?? ''),
        default    => 'Amistoso',
    };
}

/** Resultado con penaltis si los hubo */
function score_label(array $m): string
{
    $t = (int)$m['home_goals'] . ' - ' . (int)$m['away_goals'];
    if (isset($m['home_pens']) && $m['home_pens'] !== null) {
        $t .= ' (' . (int)$m['home_pens'] . '-' . (int)$m['away_pens'] . ' pen.)';
    }
    return $t;
}

function flag(?string $code): string
{
    return $code ? '<span class="flag" title="' . e($code) . '">' . NationService::flag($code) . '</span>' : '';
}

function nation_link(int|string $countryId, string $name, ?string $code = null): string
{
    return ($code ? flag($code) . ' ' : '') . '<a href="' . e(url('nation', ['id' => $countryId])) . '">' . e($name) . '</a>';
}

/* --------------------------- Imágenes --------------------------------- */

const AVATAR_COUNT = 400;

function avatar_url(array $p): string
{
    $n = (int)($p['avatar'] ?? 0);
    if ($n < 1 || $n > AVATAR_COUNT) {
        $n = ((int)($p['id'] ?? 0) * 7) % AVATAR_COUNT + 1;
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
    $h = '<div class="box cabinet"><div class="box-head">🏆 Vitrina de trofeos <span class="small">' . $count . ' título(s)</span></div>';
    $h .= '<div class="cabinet-inner"><div class="shelf">';
    foreach ($cups as $c) {
        $h .= '<div class="trophy trophy-cup" title="Copa de ' . e($c['country']) . '">'
            . '<img src="' . e(asset('img/trophies/trophy-cup.svg')) . '" alt="Copa">'
            . '<div class="trophy-label"><strong>Campeón de la Copa</strong><br>'
            . e(NationService::flag($c['code'])) . ' ' . e($c['country']) . ' · Temp. ' . (int)$c['season'] . '</div></div>';
    }
    if (!$count) {
        foreach ([1, 2, 3] as $lvl) {
            $h .= '<div class="trophy empty" title="Aún sin conquistar"><img src="' . e(trophy_url($lvl)) . '" alt=""><div class="trophy-label">Campeón de División ' . LeagueService::roman($lvl) . '</div></div>';
        }
    } else {
        foreach ($titles as $t) {
            $h .= '<div class="trophy" title="' . e("{$t['points']} puntos · {$t['goals_for']}:{$t['goals_against']} goles") . '">'
                . '<img src="' . e(trophy_url((int)$t['level'])) . '" alt="Trofeo de campeón">'
                . '<div class="trophy-label"><strong>Campeón División ' . LeagueService::roman((int)$t['level']) . '</strong><br>'
                . e(NationService::flag($t['code'])) . ' Serie ' . e($t['unit']) . ' · Temp. ' . (int)$t['season'] . '</div></div>';
        }
    }
    if (!$count) {
        $h .= '<div class="trophy empty" title="Aún sin conquistar"><img src="' . e(asset('img/trophies/trophy-cup.svg')) . '" alt=""><div class="trophy-label">Campeón de Copa</div></div>';
    }
    $h .= '</div></div>';
    if (!$count) {
        $h .= '<div class="box-body small muted center">' . ($own ? 'Tu vitrina está vacía. ¡Gana tu serie para conseguir el primer trofeo!' : 'Este club todavía no ha ganado ningún título.') . '</div>';
    }
    return $h . '</div>';
}
