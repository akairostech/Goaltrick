<?php
/** Edición del perfil público del mánager (v1.10): datos y cara de presidente */
$user = require_login();
$uid = (int)$user['id'];
$pageTitle = t('Editar mi perfil');
$navActive = 'club';
$skin = get('skin', '') === '' ? -1 : (int)get('skin');
$pg = max(1, (int)get('p', 1));
$back = fn() => url('manager_edit', array_filter(['skin' => $skin >= 0 ? $skin : null, 'p' => $pg > 1 ? $pg : null], fn($v) => $v !== null));

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'avatar' || $a === 'random') {
        $n = $a === 'random'
            ? ($skin >= 0 ? $skin * PRESIDENT_PER_SKIN + mt_rand(1, PRESIDENT_PER_SKIN) : mt_rand(1, PRESIDENT_COUNT))
            : (int)post('n');
        if ($n < 1 || $n > PRESIDENT_COUNT) {
            flash('error', t('Esa cara no existe.'));
        } else {
            DB::exec('UPDATE users SET avatar = ? WHERE id = ?', [$n, $uid]);
            flash('success', t('Has elegido la cara nº {n}.', ['n' => $n]));
        }
        redirect($back());
    }
    if ($a === 'profile') {
        $clean = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)post($k))), 0, $max);
        $bio = trim(str_replace("\r\n", "\n", (string)post('bio')));
        $bio = preg_replace("/\n{3,}/", "\n\n", $bio);
        $year = (int)post('birth_year');
        $country = (int)post('country_id');
        $errors = [];
        if (mb_strlen($bio) > 2000) {
            $errors[] = t('La presentación no puede superar los {n} caracteres.', ['n' => 2000]);
        }
        if ($year && ($year < (int)date('Y') - 100 || $year > (int)date('Y') - 10)) {
            $errors[] = t('El año de nacimiento no es válido.');
        }
        if ($country && !DB::value('SELECT COUNT(*) FROM countries WHERE id = ?', [$country])) {
            $errors[] = t('País no válido.');
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            DB::update('users', [
                'motto'      => $clean('motto', 140) ?: null,
                'city'       => $clean('city', 60) ?: null,
                'fav_club'   => $clean('fav_club', 60) ?: null,
                'birth_year' => $year ?: null,
                'country_id' => $country ?: null,
                'bio'        => $bio !== '' ? $bio : null,
            ], 'id = ?', [$uid]);
            flash('success', t('Perfil actualizado.'));
            redirect(url('manager', ['id' => $uid]));
        }
        redirect($back());
    }
    redirect($back());
}

$u = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
$current = president_n($u);
$countries = DB::all('SELECT id, name, code FROM countries ORDER BY name');
usort($countries, fn($a, $b) => strcoll(t($a['name']), t($b['name'])));

// Galería de caras, filtrada por tono de piel
$from = $skin >= 0 ? $skin * PRESIDENT_PER_SKIN + 1 : 1;
$to = $skin >= 0 ? $from + PRESIDENT_PER_SKIN - 1 : PRESIDENT_COUNT;
$total = $to - $from + 1;
$per = 48;
$pages = (int)ceil($total / $per);
$pg = min($pg, $pages);
$first = $from + ($pg - 1) * $per;
$pageIds = range($first, min($to, $first + $per - 1));
$params = $skin >= 0 ? ['skin' => $skin] : [];
?>
<div class="page-title">
    <h1>✏️ <?= e(t('Editar mi perfil')) ?></h1>
    <span><a class="btn btn-small btn-grey" href="<?= e(url('manager', ['id' => $uid])) ?>">👁 <?= e(t('Ver mi perfil público')) ?></a></span>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Mis datos públicos')) ?></div>
        <div class="box-body">
            <form method="post" class="mgr-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                <div class="field"><label for="motto"><?= e(t('Lema')) ?></label>
                    <input type="text" id="motto" name="motto" maxlength="140" value="<?= e($u['motto']) ?>" placeholder="<?= e(t('Ej.: «Con la cantera hasta el final»')) ?>"></div>
                <div class="form-row">
                    <div class="field"><label for="country_id"><?= e(t('País')) ?></label>
                        <select id="country_id" name="country_id">
                            <option value="0">—</option>
                            <?php foreach ($countries as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$u['country_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e(t($c['name'])) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label for="city"><?= e(t('Ciudad')) ?></label>
                        <input type="text" id="city" name="city" maxlength="60" value="<?= e($u['city']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label for="birth_year"><?= e(t('Año de nacimiento')) ?></label>
                        <input type="number" id="birth_year" name="birth_year" min="<?= (int)date('Y') - 100 ?>" max="<?= (int)date('Y') - 10 ?>" value="<?= $u['birth_year'] ? (int)$u['birth_year'] : '' ?>">
                        <div class="hint"><?= e(t('Opcional. En el perfil sólo se muestra la edad.')) ?></div></div>
                    <div class="field"><label for="fav_club"><?= e(t('Club favorito')) ?></label>
                        <input type="text" id="fav_club" name="fav_club" maxlength="60" value="<?= e($u['fav_club']) ?>" placeholder="<?= e(t('De la vida real')) ?>"></div>
                </div>
                <div class="field"><label for="bio"><?= e(t('Sobre mí')) ?></label>
                    <textarea id="bio" name="bio" rows="7" maxlength="2000" placeholder="<?= e(t('Preséntate: tu filosofía de juego, tus objetivos, tu historia como mánager...')) ?>"><?= e($u['bio']) ?></textarea>
                    <div class="hint"><?= e(t('Máximo {n} caracteres.', ['n' => 2000])) ?></div></div>
                <button class="btn" type="submit">💾 <?= e(t('Guardar perfil')) ?></button>
            </form>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Mi cara de presidente')) ?></div>
        <div class="box-body mgr-current">
            <img class="president-big" src="<?= e(president_url($current)) ?>" width="150" height="150" alt="">
            <div>
                <p><strong><?= e(t('Cara nº {n}', ['n' => $current])) ?></strong> · <?= e(t('Tono {name}', ['name' => t(PRESIDENT_SKINS[president_skin($current)][0])])) ?></p>
                <?php if (!(int)$u['avatar']): ?><p class="small muted"><?= e(t('Todavía no has elegido ninguna: ésta es la que se te asignó automáticamente.')) ?></p><?php endif; ?>
                <p class="small muted"><?= e(t('Elige tu cara en la galería de {n} presidentes. Puedes filtrarla por tono de piel.', ['n' => num(PRESIDENT_COUNT)])) ?></p>
                <form method="post" action="<?= e($back()) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="random">
                    <button class="btn btn-small btn-grey" type="submit">🎲 <?= e($skin >= 0 ? t('Una al azar de este tono') : t('Una al azar')) ?></button></form>
            </div>
        </div>
    </div>
</div>

<div class="box" id="gallery">
    <div class="box-head"><?= e(t('Galería de presidentes')) ?> <span class="small"><?= e(t('{n} caras', ['n' => num($total)])) ?></span></div>
    <div class="box-body skin-filter">
        <span class="small muted"><?= e(t('Tono de piel:')) ?></span>
        <a class="skin-chip any<?= $skin < 0 ? ' on' : '' ?>" href="<?= e(url('manager_edit')) ?>#gallery"><?= e(t('Todos')) ?></a>
        <?php foreach (PRESIDENT_SKINS as $i => [$sname, $hex]): ?>
            <a class="skin-chip<?= $skin === $i ? ' on' : '' ?>" href="<?= e(url('manager_edit', ['skin' => $i])) ?>#gallery" title="<?= e(t($sname)) ?>"><span class="skin-dot" style="background:<?= e($hex) ?>"></span><?= e(t($sname)) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= e($back()) ?>" class="art-grid face-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
        <?php foreach ($pageIds as $n): ?>
            <button type="submit" name="n" value="<?= $n ?>" class="art-item<?= (int)$u['avatar'] === $n ? ' on' : '' ?>" title="<?= e(t('Elegir la cara nº {n}', ['n' => $n])) ?>">
                <img src="<?= e(president_url($n)) ?>" width="76" height="76" alt="" loading="lazy">
                <span>nº <?= $n ?></span>
            </button>
        <?php endforeach; ?>
    </form>
    <?= paginate($total, $per, $pg, 'manager_edit', $params) ?>
</div>
