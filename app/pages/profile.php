<?php
$user = require_login();
$pageTitle = t('Mi cuenta');
$navActive = 'club';
$team = current_team();

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'password') {
        $err = Auth::changePassword((int)$user['id'], (string)post('current'), (string)post('new'), (string)post('new2'));
        flash($err ? 'error' : 'success', $err ?? t('Contraseña cambiada correctamente.'));
    } elseif ($a === 'email') {
        $email = strtolower(trim((string)post('email')));
        if (!password_verify((string)post('current'), $user['password_hash'])) {
            flash('error', t('La contraseña no es correcta.'));
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', t('Correo no válido.'));
        } elseif (DB::value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            flash('error', t('Ese correo ya está en uso.'));
        } else {
            DB::exec('UPDATE users SET email = ? WHERE id = ?', [$email, $user['id']]);
            flash('success', t('Correo actualizado.'));
        }
    } elseif ($a === 'teamname' && $team) {
        $name = trim((string)post('name'));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 40) {
            flash('error', t('El nombre debe tener entre 3 y 40 caracteres.'));
        } elseif (DB::value('SELECT COUNT(*) FROM teams WHERE name = ? AND id <> ?', [$name, $team['id']])) {
            flash('error', t('Ya existe un equipo con ese nombre.'));
        } else {
            DB::exec('UPDATE teams SET name = ?, short_name = ? WHERE id = ?', [$name, TeamService::shortName($name), $team['id']]);
            flash('success', t('Nombre del equipo actualizado.'));
        }
    } elseif ($a === 'leave' && $team) {
        if (!password_verify((string)post('current'), $user['password_hash'])) {
            flash('error', t('La contraseña no es correcta.'));
        } else {
            TeamService::release((int)$team['id']);
            Auth::logout();
            flash('info', t('Has abandonado tu equipo y cerrado sesión. Tu club pasa a estar controlado por la máquina.'));
            redirect(url());
        }
    }
    redirect(url('profile'));
}
?>
<div class="page-title"><h1><?= e(t('Mi cuenta')) ?></h1></div>
<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Datos de la cuenta')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Usuario')) ?></th><td><?= e($user['username']) ?></td></tr>
            <tr><th><?= e(t('Correo')) ?></th><td><?= e($user['email']) ?></td></tr>
            <tr><th><?= e(t('Registrado')) ?></th><td><?= e(fdate($user['created_at'])) ?></td></tr>
            <tr><th><?= e(t('Último acceso')) ?></th><td><?= e(fdate($user['last_login_at'])) ?></td></tr>
            <tr><th><?= e(t('Rol')) ?></th><td><?= e((int)$user['is_admin'] ? t('Administrador') : t('Mánager')) ?></td></tr>
        </table>
        <div class="box-body">
            <div class="field"><label><?= e(t('Idioma')) ?></label>
                <div>
                    <?php $curLang = Lang::code(); foreach (Lang::available() as $code => [$lname, $lflag]): ?>
                        <a class="btn<?= $code === $curLang ? '' : ' btn-grey' ?>" href="<?= e(lang_url($code)) ?>" hreflang="<?= e($code) ?>"<?= $code === $curLang ? ' aria-current="true"' : '' ?>><?= NationService::emojiFlagImg($lflag) ?> <?= e($lname) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="hint"><?= e(t('El idioma elegido se guarda en tu cuenta.')) ?></div>
            </div>
        </div>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Cambiar contraseña')) ?></div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="field"><label><?= e(t('Contraseña actual')) ?></label><input type="password" name="current" required autocomplete="current-password"></div>
            <div class="form-row">
                <div class="field"><label><?= e(t('Nueva contraseña')) ?></label><input type="password" name="new" minlength="8" required autocomplete="new-password"></div>
                <div class="field"><label><?= e(t('Repetir')) ?></label><input type="password" name="new2" minlength="8" required autocomplete="new-password"></div>
            </div>
            <button class="btn" type="submit"><?= e(t('Cambiar contraseña')) ?></button>
        </form>
    </div></div>
</div>
<br>
<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Cambiar correo')) ?></div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email">
            <div class="field"><label><?= e(t('Nuevo correo')) ?></label><input type="email" name="email" value="<?= e($user['email']) ?>" required></div>
            <div class="field"><label><?= e(t('Contraseña actual')) ?></label><input type="password" name="current" required></div>
            <button class="btn" type="submit"><?= e(t('Guardar')) ?></button>
        </form>
    </div></div>
    <?php if ($team): ?>
    <div class="box"><div class="box-head"><?= e(t('Mi equipo')) ?></div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="teamname">
            <div class="field"><label><?= e(t('Nombre del equipo')) ?></label><input type="text" name="name" value="<?= e($team['name']) ?>" maxlength="40" required></div>
            <button class="btn" type="submit"><?= e(t('Cambiar nombre')) ?></button>
        </form>
        <hr>
        <form method="post" data-confirm-submit="<?= e(t('¿Seguro que quieres abandonar tu equipo? Esta acción no se puede deshacer.')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="leave">
            <div class="field"><label><?= e(t('Abandonar el equipo (introduce tu contraseña)')) ?></label><input type="password" name="current" required></div>
            <button class="btn btn-danger" type="submit"><?= e(t('Abandonar equipo')) ?></button>
        </form>
    </div></div>
    <?php endif; ?>
</div>
