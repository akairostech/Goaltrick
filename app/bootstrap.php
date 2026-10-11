<?php
declare(strict_types=1);

/**
 * Arranque común para la web y para los scripts de consola.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.10.10');

spl_autoload_register(function (string $class): void {
    foreach (['lib', 'game'] as $dir) {
        $file = APP_ROOT . "/app/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_ROOT . '/app/lib/helpers.php';

Config::load(APP_ROOT . '/config.ini');

date_default_timezone_set((string)Config::get('app', 'timezone', 'UTC'));
mb_internal_encoding('UTF-8');

if (Config::bool('app', 'debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli' && !defined('GT_NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    session_name((string)Config::get('app', 'session_name', 'GTSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
