<?php
$pageTitle = t('Recuperar contraseña');
$layout = 'plain';
$mailOn = Config::bool('mail', 'enabled', false);
if (is_post()) {
    csrf_check();
    $email = trim((string)post('email'));
    if ($mailOn && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $token = Auth::createResetToken($email);
        if ($token) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = (str_starts_with(base_url(), 'http') ? '' : $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . url('reset', ['token' => $token]);
            $site = (string)Config::get('app', 'site_name', 'GoalTrick');
            $from = (string)Config::get('mail', 'from', 'no-reply@localhost');
            @mail($email, $site . ' - ' . t('Recuperar contraseña'),
                t("Hola,\n\nPara elegir una nueva contraseña abre este enlace (válido 1 hora):\n{link}\n\nSi no lo has pedido tú, ignora este mensaje.", ['link' => $link]),
                "From: $from\r\nContent-Type: text/plain; charset=UTF-8");
        }
    }
    // Mismo mensaje exista o no la cuenta, para no revelar qué correos están registrados
    flash('info', t('Si el correo existe en el juego, recibirás un enlace para restablecer la contraseña.'));
    redirect(url('login'));
}
?>
<div class="auth-box box">
    <div class="box-head"><?= e(t('Recuperar contraseña')) ?></div>
    <div class="box-body">
        <?php if (!$mailOn): ?>
            <div class="alert alert-info"><?= e(t('El envío de correos no está activado en este servidor. Pide al administrador del juego que te genere un enlace de recuperación desde el panel de administración.')) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('forgot')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email"><?= e(t('Correo electrónico de tu cuenta')) ?></label>
                <input type="email" id="email" name="email" required>
            </div>
            <button class="btn btn-block" type="submit" <?= $mailOn ? '' : 'disabled' ?>><?= e(t('Enviar enlace')) ?></button>
        </form>
        <hr>
        <p class="small center"><a href="<?= e(url('login')) ?>"><?= e(t('Volver al inicio de sesión')) ?></a></p>
    </div>
</div>
