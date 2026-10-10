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
    <div class="feature"><div class="fi">🏆</div><h3><?= e(t('Ligas con ascensos')) ?></h3><p class="muted"><?= e(t('Temporadas de 14 jornadas en series de 8 equipos, con ascensos y descensos, en {n} países.', ['n' => $stats['nations']])) ?></p></div>
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

$pageTitle = t('Mi club');
$navActive = 'club';
$unit = DB::row('SELECT * FROM league_units WHERE id = ?', [$team['league_unit_id']]);
$season = GameState::season();
$standing = null;
foreach (LeagueService::standings((int)$team['league_unit_id'], $season) as $row) {
    if ($row['team_id'] === (int)$team['id']) {
        $standing = $row;
    }
}
$last = LeagueService::lastMatch((int)$team['id']);
$next = LeagueService::nextMatch((int)$team['id']);
$news = DB::all('SELECT * FROM team_events WHERE team_id = ? ORDER BY id DESC LIMIT 20', [$team['id']]);
$squad = DB::row('SELECT COUNT(*) n, SUM(tsi) tsi, SUM(wage) wages, AVG(age_years) age, SUM(injury_weeks > 0) injured FROM players WHERE team_id = ? AND retired = 0', [$team['id']]);
$announcements = DB::all('SELECT * FROM announcements ORDER BY id DESC LIMIT 3');
$events = DB::all("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at LIMIT 5");
$sponsor = SponsorService::activeContract((int)$team['id']);
$sponsorOffers = count(SponsorService::offers((int)$team['id']));
$friendlyReq = (int)DB::value("SELECT COUNT(*) FROM friendly_requests WHERE to_team_id = ? AND status = 'pending'", [$team['id']]);
$capacity = (int)$team['seats_terraces'] + (int)$team['seats_basic'] + (int)$team['seats_roof'] + (int)$team['seats_vip'];
?>
<div class="page-title">
    <h1><?= e($team['name']) ?></h1>
    <span class="muted"><?= t('Mánager: {user} · Serie {unit}', ['user' => '<strong>' . e($user['username']) . '</strong>', 'unit' => '<a href="' . e(url('league', ['unit' => $unit['id']])) . '">' . e($unit['name']) . '</a>']) ?></span>
</div>

<?php if ($sponsorOffers): ?>
    <div class="alert alert-info">💼 <?= e(t('Tienes {n} ofertas de patrocinio esperando respuesta.', ['n' => $sponsorOffers])) ?> <a href="<?= e(url('sponsors')) ?>"><?= e(t('Elegir patrocinador')) ?></a></div>
<?php endif; ?>
<?php if ($friendlyReq): ?>
    <div class="alert alert-info"><?= e(t('Tienes {n} invitación(es) para jugar un amistoso.', ['n' => $friendlyReq])) ?> <a href="<?= e(url('friendlies')) ?>"><?= e(t('Responder')) ?></a></div>
<?php endif; ?>
<?php if ((int)$team['cash'] < 0): ?>
    <div class="alert alert-error"><?= e(t('Tu club tiene saldo negativo. Vende jugadores o reduce gastos cuanto antes.')) ?></div>
<?php endif; ?>

<div class="stat-tiles">
    <div class="tile"><div class="tv <?= (int)$team['cash'] < 0 ? 'neg' : '' ?>"><?= e(money($team['cash'])) ?></div><div class="tl"><?= e(t('Caja')) ?></div></div>
    <div class="tile"><div class="tv"><?= $standing ? $standing['pos'] . 'º' : '-' ?></div><div class="tl"><?= e(t('Posición en liga')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($squad['tsi']) ?></div><div class="tl"><?= e(t('TSI de la plantilla')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($team['fans']) ?></div><div class="tl"><?= e(t('Socios')) ?></div></div>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Último partido')) ?> <?php if ($last): ?><a href="<?= e(url('match', ['id' => $last['id']])) ?>"><?= e(t('Ver crónica')) ?> »</a><?php endif; ?></div>
        <div class="box-body center">
            <?php if ($last): ?>
                <div class="muted small"><?= e(match_type_label($last)) ?> · <?= e(fdate($last['played_at'])) ?></div>
                <div style="font-size:15px;margin:6px 0">
                    <?= e($last['home_name']) ?> <strong style="font-size:20px">&nbsp;<?= e(score_label($last)) ?>&nbsp;</strong> <?= e($last['away_name']) ?>
                </div>
                <div class="muted small"><?= e(t('Espectadores: {n}', ['n' => num($last['attendance'])])) ?></div>
            <?php else: ?>
                <p class="muted"><?= e(t('Todavía no has jugado ningún partido.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Próximo partido')) ?> <a href="<?= e(url('lineup')) ?>"><?= e(t('Alineación')) ?> »</a></div>
        <div class="box-body center">
            <?php if ($next): ?>
                <div class="muted small"><?= e(match_type_label($next)) ?></div>
                <div style="font-size:15px;margin:6px 0"><?= e($next['home_name']) ?> <span class="muted">vs</span> <?= e($next['away_name']) ?></div>
                <div><?= e(fdate_long($next['scheduled_at'])) ?> · <span class="countdown" style="font-size:13px" data-deadline="<?= e(date('c', strtotime($next['scheduled_at']))) ?>"></span></div>
            <?php else: ?>
                <p class="muted"><?= e(t('No hay partidos programados.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<br>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Resumen del club')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Jugadores')) ?></th><td><?= e(t('{n} ({injured} lesionados)', ['n' => (int)$squad['n'], 'injured' => (int)$squad['injured']])) ?></td></tr>
            <tr><th><?= e(t('Edad media')) ?></th><td><?= e(t('{n} años', ['n' => num($squad['age'], 1)])) ?></td></tr>
            <tr><th><?= e(t('Salarios semanales')) ?></th><td><?= e(money($squad['wages'])) ?></td></tr>
            <tr><th><?= e(t('Entrenamiento')) ?></th><td><a href="<?= e(url('training')) ?>"><?= e(skill_title($team['training_type'])) ?></a> (<?= (int)$team['training_intensity'] ?>%)</td></tr>
            <tr><th><?= e(t('Estadio')) ?></th><td><a href="<?= e(url('stadium')) ?>"><?= e($team['stadium_name']) ?></a> · <?= e(t('{n} plazas', ['n' => num($capacity)])) ?></td></tr>
            <tr><th><?= e(t('Patrocinador')) ?></th><td><?php if ($sponsor): ?><a href="<?= e(url('sponsors')) ?>"><img src="<?= e(SponsorService::logoUrl((int)$sponsor['company_id'])) ?>" width="22" height="22" class="avatar" alt=""> <?= e($sponsor['company']) ?></a> · <?= e(t('{amount}/sem.', ['amount' => money($sponsor['weekly_amount'])])) ?><?php else: ?><a href="<?= e(url('sponsors')) ?>"><?= e(t('Sin patrocinador')) ?></a><?php endif; ?></td></tr>
            <tr><th><?= e(t('Ánimo de la afición')) ?></th><td><?= e(mood_name($team['fan_mood'], 'fans')) ?></td></tr>
            <tr><th><?= e(t('Espíritu del equipo')) ?></th><td><?= e(mood_name($team['team_spirit'])) ?></td></tr>
            <tr><th><?= e(t('Confianza')) ?></th><td><?= e(mood_name($team['confidence'], 'confidence')) ?></td></tr>
            <?php if ($standing): ?>
            <tr><th><?= e(t('Liga')) ?></th><td><?= e(t('{pj} PJ · {g}G {e}E {p}P · {gf}:{gc} · {pts} pts', ['pj' => $standing['pj'], 'g' => $standing['g'], 'e' => $standing['e'], 'p' => $standing['p'], 'gf' => $standing['gf'], 'gc' => $standing['gc'], 'pts' => $standing['pts']])) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Calendario del juego')) ?></div>
        <table class="table compact">
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td><?= e(t(Scheduler::eventName($ev['type']))) ?><?= $ev['round'] ? ' ' . (int)$ev['round'] : '' ?></td>
                    <td class="num"><?= e(fdate_long($ev['run_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<br>
<?= trophy_cabinet((int)$team['id'], true) ?>

<?php foreach ($announcements as $a): ?>
<div class="box">
    <div class="box-head">📢 <?= e(tdb($a['title'])) ?> <span class="muted small"><?= e(fdate($a['created_at'])) ?></span></div>
    <div class="box-body"><?= nl2br(e(tdb($a['body']))) ?></div>
</div>
<?php endforeach; ?>

<div class="box">
    <div class="box-head"><?= e(t('Noticias del club')) ?></div>
    <?php if ($news): ?>
        <ul class="news">
            <?php foreach ($news as $n): ?>
                <li><span class="when"><?= e(fdate($n['created_at'])) ?></span><span><?= e(tdb($n['message'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="box-body muted"><?= e(t('Sin noticias.')) ?></div>
    <?php endif; ?>
</div>
