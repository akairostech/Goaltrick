<?php
$pageTitle = 'Registro';
$layout = 'plain';
if (current_user()) {
    redirect(url());
}
$open = Config::bool('app', 'registration_open', true);
$data = ['username' => '', 'email' => '', 'team' => '', 'country' => 1];
$countries = DB::all('SELECT c.id, c.name, c.code, (SELECT COUNT(*) FROM teams t WHERE t.country_id = c.id AND t.user_id IS NULL AND t.is_national = 0 AND t.league_unit_id IS NOT NULL) AS free FROM countries c ORDER BY c.id = 1 DESC, c.name');
$errors = [];
$free = (int)DB::value('SELECT COUNT(*) FROM teams WHERE user_id IS NULL AND is_national = 0');

if (is_post() && $open) {
    csrf_check();
    $data = [
        'username' => trim((string)post('username')),
        'email'    => trim((string)post('email')),
        'team'     => trim((string)post('team')),
        'country'  => (int)post('country', 1),
    ];
    // Pregunta anti-bots sencilla
    $captchaOk = isset($_SESSION['captcha']) && (int)post('captcha') === (int)$_SESSION['captcha'];
    $errors = Auth::validateRegistration($data['username'], $data['email'], (string)post('password'), (string)post('password2'), $data['team']);
    if (!$captchaOk) {
        $errors[] = 'La respuesta a la pregunta de seguridad no es correcta.';
    }
    if (!post('terms')) {
        $errors[] = 'Debes aceptar las normas del juego.';
    }
    if (!$errors) {
        try {
            $uid = Auth::register($data['username'], $data['email'], (string)post('password'), $data['team'], false, $data['country'] ?: null);
            Auth::loginUser($uid);
            flash('success', '¡Cuenta creada! Ya eres el entrenador de ' . $data['team'] . '.');
            redirect(url());
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$a = mt_rand(1, 9);
$b = mt_rand(1, 9);
$_SESSION['captcha'] = $a + $b;
?>
<div class="auth-box box" style="max-width:520px">
    <div class="box-head">Crea tu cuenta y dirige tu propio club</div>
    <div class="box-body">
        <?php if (!$open): ?>
            <div class="alert alert-warning">El registro de nuevos usuarios está cerrado temporalmente.</div>
        <?php elseif ($free === 0): ?>
            <div class="alert alert-warning">Ahora mismo no quedan equipos libres. Inténtalo más tarde.</div>
        <?php else: ?>
            <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
            <p class="muted">Recibirás un equipo en la división más baja con una plantilla, un estadio y dinero para empezar. Quedan <strong><?= num($free) ?></strong> equipos libres.</p>
            <form method="post" action="<?= e(url('register')) ?>">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="field">
                        <label for="username">Nombre de usuario</label>
                        <input type="text" id="username" name="username" value="<?= e($data['username']) ?>" required maxlength="30" pattern="[A-Za-z0-9_\-\.]{3,30}" autocomplete="username">
                        <div class="hint">3-30 caracteres: letras, números, _ - .</div>
                    </div>
                    <div class="field">
                        <label for="email">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required maxlength="190" autocomplete="email">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                        <div class="hint">Mínimo 8 caracteres.</div>
                    </div>
                    <div class="field">
                        <label for="password2">Repite la contraseña</label>
                        <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <div class="field">
                    <label for="team">Nombre de tu equipo</label>
                    <input type="text" id="team" name="team" value="<?= e($data['team']) ?>" required maxlength="40" placeholder="Ej: Deportivo Los Halcones">
                </div>
                <div class="field">
                    <label for="country">País de tu equipo</label>
                    <select id="country" name="country">
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"<?= (int)$data['country'] === (int)$c['id'] ? ' selected' : '' ?><?= (int)$c['free'] ? '' : ' disabled' ?>><?= e(NationService::flag($c['code']) . ' ' . $c['name']) ?> (<?= (int)$c['free'] ?> equipos libres)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Empezarás en la división más baja de la liga de ese país.</div>
                </div>
                <div class="field">
                    <label for="captcha">Pregunta de seguridad: ¿cuánto es <?= $a ?> + <?= $b ?>?</label>
                    <input type="number" id="captcha" name="captcha" required style="max-width:120px">
                </div>
                <div class="field">
                    <label class="checkbox"><input type="checkbox" name="terms" value="1" required> Acepto jugar limpio: una cuenta por persona y respeto a los demás usuarios.</label>
                </div>
                <button class="btn btn-block btn-accent" type="submit">Crear mi club</button>
            </form>
        <?php endif; ?>
        <hr>
        <p class="small center">¿Ya tienes cuenta? <a href="<?= e(url('login')) ?>">Inicia sesión</a></p>
    </div>
</div>
