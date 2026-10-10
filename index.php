<?php
declare(strict_types=1);

/**
 * GoalTrick - controlador frontal. Todas las páginas pasan por aquí: index.php?page=xxx
 */

require __DIR__ . '/app/bootstrap.php';

if (!Installer::isInstalled()) {
    header('Location: install.php');
    exit;
}
try {
    $needsUpgrade = Installer::needsUpgrade();
} catch (PDOException $e) {
    $needsUpgrade = false;   // sin conexión: el error se muestra más abajo al cargar la página
}
if ($needsUpgrade) {
    header('Location: upgrade.php');
    exit;
}

const PAGES = [
    // públicas
    'home' => 'home', 'login' => 'login', 'register' => 'register', 'logout' => 'logout',
    'forgot' => 'forgot', 'reset' => 'reset', 'cron' => 'cron', 'help' => 'help',
    // juego
    'players' => 'players', 'player' => 'player', 'lineup' => 'lineup', 'training' => 'training',
    'matches' => 'matches', 'match' => 'match', 'league' => 'league', 'team' => 'team',
    'transfers' => 'transfers', 'finances' => 'finances', 'stadium' => 'stadium', 'staff' => 'staff',
    'youth' => 'youth', 'friendlies' => 'friendlies', 'messages' => 'messages', 'world' => 'world',
    'profile' => 'profile', 'admin' => 'admin', 'manager' => 'manager', 'manager_edit' => 'manager_edit',
    'nations' => 'nations', 'nation' => 'nation', 'worldcup' => 'worldcup',
    'sponsors' => 'sponsors', 'companies' => 'companies', 'club' => 'club', 'staff_member' => 'staff_member', 'cup' => 'cup', 'stadium_art' => 'stadium_art',
    'admin_team' => 'admin_team', 'admin_tickets' => 'admin_tickets', 'admin_user' => 'admin_user', 'support' => 'support', 'admin_player' => 'admin_player', 'admin_reset' => 'admin_reset',
];

// Cambio de idioma: index.php?...&lang=en  (se guarda en una cookie y en el perfil del usuario)
if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    Lang::choose($_GET['lang']);
    $q = $_GET;
    unset($q['lang']);
    redirect('index.php' . ($q ? '?' . http_build_query($q) : ''));
}

$page = (string)($_GET['page'] ?? 'home');
if (!isset(PAGES[$page])) {
    http_response_code(404);
    $page = 'home';
    flash('error', t('La página solicitada no existe.'));
}

try {
    // Ejecutar tareas pendientes (partidos, entrenamiento...) si está activado
    if (Config::bool('game', 'auto_update', true) && $page !== 'cron') {
        Scheduler::tickFromWeb();
    }

    $pageTitle = '';
    $layout = 'game';     // game | plain
    $navActive = 'club';
    ob_start();
    require __DIR__ . '/app/pages/' . PAGES[$page] . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/app/views/layout.php';
} catch (PDOException $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    $msg = Config::bool('app', 'debug') ? $e->getMessage() : t('Error de base de datos. Revisa la configuración en config.ini.');
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:sans-serif;padding:40px">'
        . '<h2>Error de base de datos</h2><p>' . e($msg) . '</p></div>';
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    error_log((string)$e);
    $msg = Config::bool('app', 'debug') ? $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() : 'Ha ocurrido un error inesperado.';
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:sans-serif;padding:40px">'
        . '<h2>Error</h2><p>' . e($msg) . '</p>'
        . (Config::bool('app', 'debug') ? '<pre>' . e($e->getTraceAsString()) . '</pre>' : '') . '</div>';
}
