<?php
$pageTitle = 'Nueva contraseña';
$layout = 'plain';
$token = (string)(post('token') ?: get('token'));
if (is_post()) {
    csrf_check();
    $err = Auth::resetPassword($token, (string)post('password'), (string)post('password2'));
    if ($err === null) {
        flash('success', 'Contraseña cambiada. Ya puedes iniciar sesión.');
        redirect(url('login'));
    }
    flash('error', $err);
    redirect(url('reset', ['token' => $token]));
}
?>
<div class="auth-box box">
    <div class="box-head">Elige una nueva contraseña</div>
    <div class="box-body">
        <form method="post" action="<?= e(url('reset')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="field">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password2">Repite la contraseña</label>
                <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-block" type="submit">Guardar contraseña</button>
        </form>
    </div>
</div>
