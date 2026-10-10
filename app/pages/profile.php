<?php
$user = require_login();
$pageTitle = 'Mi cuenta';
$navActive = 'club';
$team = current_team();

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'password') {
        $err = Auth::changePassword((int)$user['id'], (string)post('current'), (string)post('new'), (string)post('new2'));
        flash($err ? 'error' : 'success', $err ?? 'Contraseña cambiada correctamente.');
    } elseif ($a === 'email') {
        $email = strtolower(trim((string)post('email')));
        if (!password_verify((string)post('current'), $user['password_hash'])) {
            flash('error', 'La contraseña no es correcta.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Correo no válido.');
        } elseif (DB::value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            flash('error', 'Ese correo ya está en uso.');
        } else {
            DB::exec('UPDATE users SET email = ? WHERE id = ?', [$email, $user['id']]);
            flash('success', 'Correo actualizado.');
        }
    } elseif ($a === 'teamname' && $team) {
        $name = trim((string)post('name'));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 40) {
            flash('error', 'El nombre debe tener entre 3 y 40 caracteres.');
        } elseif (DB::value('SELECT COUNT(*) FROM teams WHERE name = ? AND id <> ?', [$name, $team['id']])) {
            flash('error', 'Ya existe un equipo con ese nombre.');
        } else {
            DB::exec('UPDATE teams SET name = ?, short_name = ? WHERE id = ?', [$name, TeamService::shortName($name), $team['id']]);
            flash('success', 'Nombre del equipo actualizado.');
        }
    } elseif ($a === 'leave' && $team) {
        if (!password_verify((string)post('current'), $user['password_hash'])) {
            flash('error', 'La contraseña no es correcta.');
        } else {
            TeamService::release((int)$team['id']);
            Auth::logout();
            flash('info', 'Has abandonado tu equipo y cerrado sesión. Tu club pasa a estar controlado por la máquina.');
            redirect(url());
        }
    }
    redirect(url('profile'));
}
?>
<div class="page-title"><h1>Mi cuenta</h1></div>
<div class="grid-2">
    <div class="box"><div class="box-head">Datos de la cuenta</div>
        <table class="kv">
            <tr><th>Usuario</th><td><?= e($user['username']) ?></td></tr>
            <tr><th>Correo</th><td><?= e($user['email']) ?></td></tr>
            <tr><th>Registrado</th><td><?= e(fdate($user['created_at'])) ?></td></tr>
            <tr><th>Último acceso</th><td><?= e(fdate($user['last_login_at'])) ?></td></tr>
            <tr><th>Rol</th><td><?= (int)$user['is_admin'] ? 'Administrador' : 'Mánager' ?></td></tr>
        </table>
    </div>
    <div class="box"><div class="box-head">Cambiar contraseña</div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="field"><label>Contraseña actual</label><input type="password" name="current" required autocomplete="current-password"></div>
            <div class="form-row">
                <div class="field"><label>Nueva contraseña</label><input type="password" name="new" minlength="8" required autocomplete="new-password"></div>
                <div class="field"><label>Repetir</label><input type="password" name="new2" minlength="8" required autocomplete="new-password"></div>
            </div>
            <button class="btn" type="submit">Cambiar contraseña</button>
        </form>
    </div></div>
</div>
<br>
<div class="grid-2">
    <div class="box"><div class="box-head">Cambiar correo</div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email">
            <div class="field"><label>Nuevo correo</label><input type="email" name="email" value="<?= e($user['email']) ?>" required></div>
            <div class="field"><label>Contraseña actual</label><input type="password" name="current" required></div>
            <button class="btn" type="submit">Guardar</button>
        </form>
    </div></div>
    <?php if ($team): ?>
    <div class="box"><div class="box-head">Mi equipo</div><div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="teamname">
            <div class="field"><label>Nombre del equipo</label><input type="text" name="name" value="<?= e($team['name']) ?>" maxlength="40" required></div>
            <button class="btn" type="submit">Cambiar nombre</button>
        </form>
        <hr>
        <form method="post" data-confirm-submit="¿Seguro que quieres abandonar tu equipo? Esta acción no se puede deshacer."><?= csrf_field() ?><input type="hidden" name="action" value="leave">
            <div class="field"><label>Abandonar el equipo (introduce tu contraseña)</label><input type="password" name="current" required></div>
            <button class="btn btn-danger" type="submit">Abandonar equipo</button>
        </form>
    </div></div>
    <?php endif; ?>
</div>
