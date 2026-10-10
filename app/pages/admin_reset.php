<?php
/** Administración: reiniciar por completo la partida */
$admin = require_admin();
$pageTitle = t('Reiniciar partida');
$navActive = 'admin';
$log = [];
$error = null;
$done = false;

if (is_post()) {
    csrf_check();
    if (trim((string)post('confirm')) !== 'REINICIAR') {
        $error = t('Escribe REINICIAR (en mayúsculas) para confirmar.');
    } elseif (!password_verify((string)post('password'), $admin['password_hash'])) {
        $error = t('La contraseña no es correcta.');
    } else {
        try {
            Installer::resetGame((int)$admin['id'], (bool)post('keep_users'), (bool)post('keep_messages'), function (string $m) use (&$log) { $log[] = $m; });
            $done = true;
        } catch (Throwable $e) {
            $error = t('Error durante el reinicio: {error}', ['error' => $e->getMessage()]);
        }
    }
}
$stats = DB::row('SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM teams WHERE user_id IS NOT NULL) h, (SELECT COUNT(*) FROM matches WHERE status = \'played\') m');
?>
<div class="page-title"><h1><?= e(t('Reiniciar la partida')) ?></h1><a href="<?= e(url('admin')) ?>">« <?= e(t('Volver a Administración')) ?></a></div>

<?php if ($done): ?>
    <div class="alert alert-success"><?= e(t('La partida se ha reiniciado. Empieza la temporada 1.')) ?></div>
    <div class="box"><div class="box-body"><ul><?php foreach ($log as $l): ?><li><?= e(tdb($l)) ?></li><?php endforeach; ?></ul>
        <a class="btn btn-accent" href="<?= e(url()) ?>"><?= e(t('Ir a mi nuevo club')) ?></a></div></div>
<?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="alert alert-warning">
        <strong>⚠️ <?= e(t('Atención: esta acción no se puede deshacer.')) ?></strong> <?= e(t('Se borrarán todas las ligas, equipos, jugadores, partidos ({n} jugados), fichajes, finanzas, historiales, Mundiales y anuncios, y se generará un mundo nuevo empezando por la temporada 1.', ['n' => num($stats['m'])])) ?>
        <?= e(t('Haz antes una copia de seguridad de la base de datos (por ejemplo, exportándola desde phpMyAdmin) si quieres conservarla.')) ?>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Confirmar reinicio')) ?></div><div class="box-body">
        <form method="post" data-confirm-submit="<?= e(t('Última confirmación: ¿borrar TODA la partida y empezar de cero?')) ?>">
            <?= csrf_field() ?>
            <label class="checkbox"><input type="checkbox" name="keep_users" value="1" checked> <?= e(t('Conservar las cuentas de usuario ({n}). Cada mánager recibirá un equipo nuevo, con el mismo nombre y en su mismo país.', ['n' => num($stats['u'])])) ?></label>
            <p class="small muted" style="margin-left:24px"><?= e(t('Si lo desmarcas se borrarán todas las cuentas excepto la tuya.')) ?></p>
            <label class="checkbox"><input type="checkbox" name="keep_messages" value="1" checked> <?= e(t('Conservar los mensajes privados')) ?></label>
            <br>
            <div class="form-row">
                <div class="field"><label><?= e(t('Escribe REINICIAR para confirmar')) ?></label><input type="text" name="confirm" autocomplete="off" required></div>
                <div class="field"><label><?= e(t('Tu contraseña de administrador')) ?></label><input type="password" name="password" required autocomplete="current-password"></div>
            </div>
            <p class="small muted"><?= e(t('El proceso puede tardar uno o dos minutos (se generan unos 770 equipos y 14.000 jugadores). No cierres la página.')) ?></p>
            <button class="btn btn-danger" type="submit"><?= e(t('Reiniciar la partida por completo')) ?></button>
        </form>
    </div></div>
<?php endif; ?>
