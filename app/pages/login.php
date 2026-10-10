<?php
$pageTitle = t('Iniciar sesión');
$layout = 'plain';
if (current_user()) {
    redirect(url());
}
$login = '';
if (is_post()) {
    csrf_check();
    $login = trim((string)post('login'));
    $err = Auth::attempt($login, (string)post('password'), (bool)post('remember'));
    if ($err === null) {
        flash('success', t('¡Bienvenido de nuevo!'));
        redirect(url());
    }
    flash('error', $err);
    redirect(url('login'));
}
?>
<div class="auth-box box">
    <div class="box-head"><?= e(t('Iniciar sesión')) ?></div>
    <div class="box-body">
        <form method="post" action="<?= e(url('login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="login"><?= e(t('Usuario o correo electrónico')) ?></label>
                <input type="text" id="login" name="login" value="<?= e($login) ?>" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password"><?= e(t('Contraseña')) ?></label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label class="checkbox"><input type="checkbox" name="remember" value="1"> <?= e(t('Recordarme en este equipo')) ?></label>
            </div>
            <button class="btn btn-block" type="submit"><?= e(t('Entrar')) ?></button>
        </form>
        <hr>
        <p class="small center">
            <a href="<?= e(url('forgot')) ?>"><?= e(t('¿Olvidaste tu contraseña?')) ?></a> ·
            <a href="<?= e(url('register')) ?>"><?= e(t('Crear una cuenta')) ?></a>
        </p>
    </div>
</div>
