<?php
$user = current_user();
$team = current_team();
$site = (string)Config::get('app', 'site_name', 'GoalTrick');

if (!$user):
    $pageTitle = t('Bienvenido');
    $layout = 'plain';
    $stats = [
        'users'   => (int)DB::value('SELECT COUNT(*) FROM users'),
        'teams'   => (int)DB::value('SELECT COUNT(*) FROM teams WHERE is_national = 0'),
        'matches' => (int)DB::value("SELECT COUNT(*) FROM matches WHERE status = 'played'"),
        'free'    => (int)DB::value('SELECT COUNT(*) FROM teams WHERE user_id IS NULL AND is_national = 0'),
        'nations' => (int)DB::value('SELECT COUNT(*) FROM countries'),
    ];
?>
<section class="hero">
    <h1><?= e(t('Dirige tu propio club de fútbol')) ?></h1>
    <p><?= e(t('Ficha jugadores, entrénalos semana a semana, elige la táctica y la alineación, amplía tu estadio y lleva a tu equipo desde la última división hasta lo más alto. Gratis y desde el navegador.')) ?></p>
    <a class="btn btn-accent" href="<?= e(url('register')) ?>"><?= e(t('¡Quiero mi equipo!')) ?></a>
    <a class="btn btn-ghost" href="<?= e(url('login')) ?>"><?= e(t('Ya tengo cuenta')) ?></a>
</section>

<div class="stat-tiles">
    <div class="tile"><div class="tv"><?= num($stats['users']) ?></div><div class="tl"><?= e(t('Mánagers')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($stats['teams']) ?></div><div class="tl"><?= e(t('Equipos')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($stats['matches']) ?></div><div class="tl"><?= e(t('Partidos jugados')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($stats['free']) ?></div><div class="tl"><?= e(t('Equipos libres')) ?></div></div>
</div>

<div class="features">
    <div class="feature"><div class="fi">📋</div><h3><?= e(t('Tácticas y alineaciones')) ?></h3><p class="muted"><?= e(t('14 posiciones, órdenes individuales, 6 tácticas de equipo y la actitud para cada partido.')) ?></p></div>
    <div class="feature"><div class="fi">🏋️</div><h3><?= e(t('Entrenamiento semanal')) ?></h3><p class="muted"><?= e(t('Elige qué entrenar y quién juega dónde. Los jóvenes mejoran rápido; los veteranos aportan experiencia.')) ?></p></div>
    <div class="feature"><div class="fi">⚽</div><h3><?= e(t('Motor de partidos')) ?></h3><p class="muted"><?= e(t('Valoraciones por sectores, ocasiones, balón parado, contraataques, especialidades, tarjetas y lesiones.')) ?></p></div>
    <div class="feature"><div class="fi">💰</div><h3><?= e(t('Mercado de fichajes')) ?></h3><p class="muted"><?= e(t('Subastas en tiempo real con otros mánagers y con los clubes de la máquina.')) ?></p></div>
    <div class="feature"><div class="fi">🏟</div><h3><?= e(t('Economía y estadio')) ?></h3><p class="muted"><?= e(t('Taquilla, patrocinadores, salarios, personal, cantera y ampliaciones del estadio.')) ?></p></div>
    <div class="feature"><div class="fi">🏆</div><h3><?= e(t('Ligas con ascensos')) ?></h3><p class="muted"><?= e(t('Temporadas de 14 jornadas en divisiones de 8 equipos, con ascensos y descensos, en {n} países.', ['n' => $stats['nations']])) ?></p></div>
</div>
<?php
    return;
endif;

if (!$team) {
    $pageTitle = t('Sin equipo');
    if (is_post() && post('action') === 'newteam') {
        csrf_check();
        $name = trim((string)post('name'));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 40 || DB::value('SELECT COUNT(*) FROM teams WHERE name = ?', [$name])) {
            flash('error', t('Nombre de equipo no válido o ya en uso.'));
        } elseif (!($free = TeamService::findFreeTeam())) {
            flash('error', t('No quedan equipos libres.'));
        } else {
            DB::transaction(fn() => TeamService::assignToUser($free, (int)$user['id'], $name));
            flash('success', t('¡Ya tienes un nuevo equipo!'));
        }
        redirect(url());
    }
    ?>
    <div class="box auth-box"><div class="box-head"><?= e(t('No tienes equipo')) ?></div><div class="box-body">
        <p><?= e(t('Puedes hacerte cargo de un equipo libre de la división más baja.')) ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="newteam">
            <div class="field"><label><?= e(t('Nombre del nuevo equipo')) ?></label><input type="text" name="name" maxlength="40" required></div>
            <button class="btn btn-block" type="submit"><?= e(t('Obtener equipo')) ?></button>
        </form>
    </div></div>
    <?php
    return;
}

// El resumen del club, el uniforme y el escudo están en club.php
require __DIR__ . '/club.php';
