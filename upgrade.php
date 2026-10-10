<?php
declare(strict_types=1);

/**
 * Actualizador de GoalTrick: aplica los cambios de base de datos de las versiones nuevas
 * (sin borrar nada de tu partida). Se abre automáticamente cuando hace falta.
 */

require __DIR__ . '/app/bootstrap.php';

$site = (string)Config::get('app', 'site_name', 'GoalTrick');
if (!Installer::isInstalled()) {
    header('Location: install.php');
    exit;
}
$log = [];
$error = null;
$done = false;
if (Installer::needsUpgrade() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    @set_time_limit(0);
    try {
        Installer::upgrade(function (string $m) use (&$log) { $log[] = $m; });
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$pending = Installer::needsUpgrade();
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actualización · <?= e($site) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="layout-plain">
<header class="topbar"><div class="wrap topbar-inner"><span class="logo"><span class="logo-ball">⚽</span><span class="logo-text"><?= e($site) ?></span></span><span>Actualización</span></div></header>
<div class="wrap page">
<main class="content">
    <div class="auth-box box" style="max-width:640px">
        <div class="box-head">Actualizar la base de datos</div>
        <div class="box-body">
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($log): ?><ul><?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($done || !$pending): ?>
                <div class="alert alert-success">La base de datos está al día (versión <?= Installer::DB_VERSION ?>).</div>
                <a class="btn btn-accent" href="index.php">Ir al juego</a>
            <?php else: ?>
                <p>Has instalado una versión nueva del juego. Hay que actualizar la base de datos (versión <?= Installer::dbVersion() ?> → <?= Installer::DB_VERSION ?>). <strong>No se borra nada de tu partida.</strong></p>
                <p>Novedades de la versión 1.4:</p>
                <ul>
                    <li><strong>32 naciones</strong> con sus ligas y un Mundial de 32 selecciones (8 grupos + octavos).</li>
                    <li><strong>Copa</strong> de cada país a eliminación directa, jugada en la fecha de los amistosos, con su trofeo en la vitrina.</li>
                    <li>Entradas dinámicas al estadio (socios, ánimo, confianza, rival, clima) y avisos por mensaje cuando el estadio se llena o se queda vacío.</li>
                    <li>Perfil para el entrenador y el personal: nombre, edad, nacionalidad, carácter e imagen.</li>
                    <li>Nueva alineación con arrastrar y soltar y ficha emergente de cada jugador.</li>
                </ul>
                <p class="small muted">La actualización crea las ligas de 16 países nuevos: puede tardar unos minutos.</p>
                <p>Novedades de la versión 1.3:</p>
                <ul>
                    <li>400 caras distintas para los jugadores.</li>
                    <li><strong>Patrocinio</strong>: 400 empresas ficticias con logo y 3 ofertas de contrato antes de cada temporada.</li>
                </ul>
                <p>Novedades de la versión 1.2:</p>
                <ul>
                    <li><strong>Vitrina de trofeos</strong> en el resumen del club.</li>
                    <li>Imagen de perfil para cada jugador (50 diseños).</li>
                    <li>«Lateral» tiene más peso en los partidos; «Condición» y «Jugadas» con los nombres de Hattrick.</li>
                    <li>Administración: editar cualquier equipo y reiniciar la partida.</li>
                </ul>
                <p>Novedades de la versión 1.1 (si vienes de la 1.0):</p>
                <ul>
                    <li>«Futbolandia» pasa a llamarse <strong>Panamá</strong>.</li>
                    <li>Nueva sección <strong>Naciones</strong>: cada país tiene su propia liga con equipos y jugadores.</li>
                    <li><strong>Selecciones nacionales</strong> formadas por los jugadores con mayor TSI de cada posición.</li>
                    <li>Nuevo torneo <strong>Mundial de Fútbol</strong> entre las 16 selecciones.</li>
                </ul>
                <p class="small muted">Se generarán las ligas de 15 países nuevos. Puede tardar uno o dos minutos: no cierres la página.</p>
                <form method="post"><?= csrf_field() ?><button class="btn btn-block btn-accent" type="submit">Actualizar ahora</button></form>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>
