<?php
declare(strict_types=1);

/**
 * Actualizador de GoalTrick: aplica los cambios de base de datos de las versiones nuevas
 * (sin borrar nada de tu partida). Se abre automáticamente cuando hace falta.
 */

require __DIR__ . '/app/bootstrap.php';

if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    Lang::choose($_GET['lang']);
}

$site = (string)Config::get('app', 'site_name', 'GoalTrick');
if (!Installer::isInstalled()) {
    header('Location: install.php');
    exit;
}
$log = [];
$error = null;
$done = false;
try {
    Installer::dbVersion();
} catch (PDOException $e) {
    http_response_code(500);
    exit('No se puede conectar con la base de datos. Revisa config.ini y que MySQL esté encendido. / Cannot connect to the database.');
}
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
<html lang="<?= e(Lang::code()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('Actualización')) ?> · <?= e($site) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="layout-plain">
<header class="topbar"><div class="wrap topbar-inner"><span class="logo"><span class="logo-ball">⚽</span><span class="logo-text"><?= e($site) ?></span></span><span><?= e(t('Actualización')) ?> <span class="lang-switch"><?php foreach (Lang::available() as $lcode => [$lname, $lflag]): ?> <a href="?lang=<?= e(urlencode($lcode)) ?>" title="<?= e($lname) ?>"<?= $lcode === Lang::code() ? ' style="font-weight:bold"' : '' ?>><?= e($lflag) ?> <?= e($lname) ?></a><?php endforeach; ?></span></span></div></header>
<div class="wrap page">
<main class="content">
    <div class="auth-box box" style="max-width:640px">
        <div class="box-head"><?= e(t('Actualizar la base de datos')) ?></div>
        <div class="box-body">
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($log): ?><ul><?php foreach ($log as $l): ?><li><?= e(tdb($l)) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($done || !$pending): ?>
                <div class="alert alert-success"><?= e(t('La base de datos está al día (versión {v}).', ['v' => Installer::DB_VERSION])) ?></div>
                <a class="btn btn-accent" href="index.php"><?= e(t('Ir al juego')) ?></a>
            <?php else: ?>
                <p><?= e(t('Has instalado una versión nueva del juego. Hay que actualizar la base de datos (versión {from} → {to}).', ['from' => Installer::dbVersion(), 'to' => Installer::DB_VERSION])) ?> <strong><?= e(t('No se borra nada de tu partida.')) ?></strong></p>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.8'])) ?></p>
                <ul>
                    <li><?= e(t('Ayuda y soporte: los usuarios abren tickets (errores, denuncias, dudas) y la administración los responde.')) ?></li>
                    <li><?= e(t('Sanciones: avisos, suspensiones temporales y expulsiones con motivo, e historial de la actividad de cada usuario.')) ?></li>
                    <li><?= e(t('La administración puede cambiar el escudo y el uniforme de cualquier equipo.')) ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.7'])) ?></p>
                <ul>
                    <li><?= e(t('«Resumen del club» y «Club» se unen en una sola sección.')) ?></li>
                    <li><?= e(t('En «Partidos»: escudos, botones de previa y de alineación, y una alineación distinta para cada partido.')) ?></li>
                    <li><?= e(t('Las series pasan a llamarse divisiones.')) ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.6'])) ?></p>
                <ul>
                    <li><?= e(t('Nueva sección «Club»: resumen general, 1000 uniformes y 1000 escudos para elegir, o sube los tuyos.')) ?></li>
                    <li><?= e(t('Nueva sección «Transferencias» con el historial de compras y ventas, totales y promedios.')) ?></li>
                    <li><?= e(t('Previa de los partidos: escudos, índice de rendimiento estimado, resumen de los equipos, pronóstico y opinión de los socios.')) ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.5'])) ?></p>
                <ul>
                    <li><?= e(t('Idiomas: español e inglés, con un selector en la barra superior. Se pueden añadir más idiomas copiando un archivo de la carpeta lang.')) ?></li>
                    <li><?= e(t('Estadio ilustrado: seis modelos que cambian según la capacidad y el tipo de localidades construidas.')) ?></li>
                    <li><?= e(t('Alineación predeterminada (se usa si no envías alineación) y hasta 3 alineaciones guardadas.')) ?></li>
                    <li><?= e(t('El Mundial de esta temporada se vuelve a sortear con las 32 selecciones.')) ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.4'])) ?></p>
                <ul>
                    <li><?= t('<strong>32 naciones</strong> con sus ligas y un Mundial de 32 selecciones (8 grupos + octavos).') ?></li>
                    <li><?= t('<strong>Copa</strong> de cada país a eliminación directa, jugada en la fecha de los amistosos, con su trofeo en la vitrina.') ?></li>
                    <li><?= e(t('Entradas dinámicas al estadio (socios, ánimo, confianza, rival, clima) y avisos por mensaje cuando el estadio se llena o se queda vacío.')) ?></li>
                    <li><?= e(t('Perfil para el entrenador y el personal: nombre, edad, nacionalidad, carácter e imagen.')) ?></li>
                    <li><?= e(t('Nueva alineación con arrastrar y soltar y ficha emergente de cada jugador.')) ?></li>
                </ul>
                <p class="small muted"><?= e(t('La actualización crea las ligas de 16 países nuevos: puede tardar unos minutos.')) ?></p>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.3'])) ?></p>
                <ul>
                    <li><?= e(t('400 caras distintas para los jugadores.')) ?></li>
                    <li><?= t('<strong>Patrocinio</strong>: 400 empresas ficticias con logo y 3 ofertas de contrato antes de cada temporada.') ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión {v}:', ['v' => '1.2'])) ?></p>
                <ul>
                    <li><?= t('<strong>Vitrina de trofeos</strong> en el resumen del club.') ?></li>
                    <li><?= e(t('Imagen de perfil para cada jugador (50 diseños).')) ?></li>
                    <li><?= e(t('«Lateral» tiene más peso en los partidos; «Condición» y «Jugadas» con los nombres de Hattrick.')) ?></li>
                    <li><?= e(t('Administración: editar cualquier equipo y reiniciar la partida.')) ?></li>
                </ul>
                <p><?= e(t('Novedades de la versión 1.1 (si vienes de la 1.0):')) ?></p>
                <ul>
                    <li><?= t('«Futbolandia» pasa a llamarse <strong>Panamá</strong>.') ?></li>
                    <li><?= t('Nueva sección <strong>Naciones</strong>: cada país tiene su propia liga con equipos y jugadores.') ?></li>
                    <li><?= t('<strong>Selecciones nacionales</strong> formadas por los jugadores con mayor TSI de cada posición.') ?></li>
                    <li><?= t('Nuevo torneo <strong>Mundial de Fútbol</strong> entre las 16 selecciones.') ?></li>
                </ul>
                <p class="small muted"><?= e(t('Se generarán las ligas de 15 países nuevos. Puede tardar uno o dos minutos: no cierres la página.')) ?></p>
                <form method="post"><?= csrf_field() ?><button class="btn btn-block btn-accent" type="submit"><?= e(t('Actualizar ahora')) ?></button></form>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>
