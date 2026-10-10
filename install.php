<?php
declare(strict_types=1);

/**
 * Instalador web de GoalTrick.
 * 1) Configura config.ini con los datos de tu servidor MySQL.
 * 2) Abre esta página en el navegador y crea la cuenta de administrador.
 * Al terminar se crea el archivo install.lock (bórralo para reinstalar).
 */

require __DIR__ . '/app/bootstrap.php';

$site = (string)Config::get('app', 'site_name', 'GoalTrick');
$checks = [];
$checks[] = ['PHP 8.1 o superior', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
$checks[] = ['Extensión pdo_mysql', extension_loaded('pdo_mysql'), ''];
$checks[] = ['Extensión mbstring', extension_loaded('mbstring'), ''];
$checks[] = ['Carpeta del juego con permiso de escritura (install.lock)', is_writable(__DIR__), ''];
$dbError = null;
try {
    DB::connect(false);
    $checks[] = ['Conexión con MySQL (' . Config::get('database', 'host') . ':' . Config::get('database', 'port') . ')', true, ''];
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $checks[] = ['Conexión con MySQL', false, $dbError];
}
$allOk = !in_array(false, array_column($checks, 1), true);
$installed = Installer::isInstalled();
$log = [];
$error = null;
$done = false;

if (!$installed && $allOk && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    @set_time_limit(0);
    try {
        Installer::install(
            trim((string)post('username')),
            trim((string)post('email')),
            (string)post('password'),
            trim((string)post('team')),
            function (string $m) use (&$log) { $log[] = $m; }
        );
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalación · <?= e($site) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="layout-plain">
<header class="topbar"><div class="wrap topbar-inner"><span class="logo"><span class="logo-ball">⚽</span><span class="logo-text"><?= e($site) ?></span></span><span>Instalación</span></div></header>
<div class="wrap page">
<main class="content">
    <div class="auth-box box" style="max-width:640px">
        <div class="box-head">Instalador</div>
        <div class="box-body">
        <?php if ($installed && !$done): ?>
            <div class="alert alert-info">El juego ya está instalado. Si quieres reinstalarlo, borra el archivo <code>install.lock</code> (y la base de datos si quieres empezar de cero).</div>
            <a class="btn" href="index.php">Ir al juego</a>
        <?php elseif ($done): ?>
            <div class="alert alert-success">¡Instalación completada!</div>
            <ul><?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
            <p>Ya puedes entrar con tu cuenta de administrador. Recuerda revisar <code>config.ini</code> (por ejemplo, cambia <code>cron_key</code> y pon <code>debug = false</code> en producción).</p>
            <a class="btn btn-accent" href="index.php?page=login">Entrar al juego</a>
        <?php else: ?>
            <h3>1. Requisitos</h3>
            <table class="table compact">
                <?php foreach ($checks as [$label, $ok, $info]): ?>
                    <tr><td><?= e($label) ?></td><td><?= $ok ? '<span class="pos">OK</span>' : '<span class="neg">ERROR</span>' ?></td><td class="small muted"><?= e($info) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php if (!$allOk): ?>
                <div class="alert alert-error" style="margin-top:10px">Corrige los errores y recarga la página. Los datos de conexión a MySQL están en <code>config.ini</code>, sección <code>[database]</code>.</div>
            <?php else: ?>
                <p class="small muted">Se usará la base de datos <strong><?= e((string)Config::get('database', 'name')) ?></strong> (se crea si no existe). Se generará la liga de «<?= e((string)Config::get('game', 'country_name')) ?>» con <?= Config::int('game', 'divisions', 3) ?> divisiones.</p>
                <h3>2. Cuenta de administrador</h3>
                <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="field"><label>Usuario</label><input type="text" name="username" value="<?= e((string)post('username', 'admin')) ?>" required></div>
                        <div class="field"><label>Correo</label><input type="email" name="email" value="<?= e((string)post('email')) ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="field"><label>Contraseña (mín. 8)</label><input type="password" name="password" minlength="8" required></div>
                        <div class="field"><label>Nombre de tu equipo</label><input type="text" name="team" value="<?= e((string)post('team')) ?>" required></div>
                    </div>
                    <button class="btn btn-block btn-accent" type="submit">Instalar</button>
                    <p class="small muted" style="margin-top:8px">La generación de equipos y jugadores puede tardar entre unos segundos y un minuto.</p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>
