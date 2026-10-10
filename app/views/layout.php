<?php
/** @var string $content */
/** @var string $pageTitle */
/** @var string $layout */
/** @var string $navActive */
$user = current_user();
$team = $user ? current_team() : null;
$site = (string)Config::get('app', 'site_name', 'GoalTrick');
$flashes = take_flashes();
$unread = $user ? (int)DB::value('SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND read_at IS NULL AND deleted_by_recipient = 0', [$user['id']]) : 0;
$currentPage = (string)($_GET['page'] ?? 'home');
$season = GameState::season();
$week = GameState::week();

$clubMenu = [
    'home'       => ['🏠', 'Resumen del club'],
    'players'    => ['👥', 'Jugadores'],
    'lineup'     => ['📋', 'Alineación'],
    'matches'    => ['⚽', 'Partidos'],
    'league'     => ['🏆', 'Liga'],
    'cup'        => ['🥇', 'Copa'],
    'training'   => ['🏋️', 'Entrenamiento'],
    'finances'   => ['💰', 'Finanzas'],
    'sponsors'   => ['💼', 'Patrocinio'],
    'stadium'    => ['🏟', 'Estadio'],
    'staff'      => ['🧑‍💼', 'Personal'],
    'youth'      => ['🌱', 'Cantera'],
    'friendlies' => ['🤝', 'Amistosos'],
];
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ? $pageTitle . ' · ' : '') . $site) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>">
</head>
<body class="layout-<?= e($layout) ?>">
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="logo" href="<?= e(url()) ?>"><span class="logo-ball">⚽</span><span class="logo-text"><?= e($site) ?></span></a>
        <div class="topbar-right">
            <?php if ($user): ?>
                <span class="tb-user">👤 <a href="<?= e(url('profile')) ?>"><?= e($user['username']) ?></a></span>
                <a class="tb-mail<?= $unread ? ' has-unread' : '' ?>" href="<?= e(url('messages')) ?>" title="Mensajes">✉️<?php if ($unread): ?><span class="badge"><?= $unread ?></span><?php endif; ?></a>
                <span class="tb-season">Temporada <?= $season ?> · Semana <?= $week ?></span>
                <a class="btn btn-small btn-ghost" href="<?= e(url('logout', ['t' => csrf_token()])) ?>">Salir</a>
            <?php else: ?>
                <a class="btn btn-small btn-ghost" href="<?= e(url('login')) ?>">Iniciar sesión</a>
                <a class="btn btn-small btn-accent" href="<?= e(url('register')) ?>">Regístrate gratis</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($user): ?>
    <nav class="mainnav">
        <div class="wrap">
            <a class="<?= $navActive === 'club' ? 'active' : '' ?>" href="<?= e(url()) ?>">Mi club</a>
            <a class="<?= $navActive === 'league' ? 'active' : '' ?>" href="<?= e(url('league')) ?>">Liga</a>
            <a class="<?= $navActive === 'transfers' ? 'active' : '' ?>" href="<?= e(url('transfers')) ?>">Mercado</a>
            <a class="<?= $navActive === 'nations' ? 'active' : '' ?>" href="<?= e(url('nations')) ?>">Naciones</a>
            <a class="<?= $navActive === 'worldcup' ? 'active' : '' ?>" href="<?= e(url('worldcup')) ?>">Mundial</a>
            <a class="<?= $navActive === 'world' ? 'active' : '' ?>" href="<?= e(url('world')) ?>">Mundo</a>
            <a class="<?= $navActive === 'community' ? 'active' : '' ?>" href="<?= e(url('messages')) ?>">Comunidad<?php if ($unread): ?> <span class="badge"><?= $unread ?></span><?php endif; ?></a>
            <a class="<?= $navActive === 'help' ? 'active' : '' ?>" href="<?= e(url('help')) ?>">Ayuda</a>
            <?php if ((int)$user['is_admin']): ?>
                <a class="nav-admin <?= $navActive === 'admin' ? 'active' : '' ?>" href="<?= e(url('admin')) ?>">Administración</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</header>

<div class="wrap page">
    <?php if ($layout === 'game' && $team): ?>
    <aside class="sidebar-left">
        <div class="box">
            <div class="box-head"><?= e($team['name']) ?></div>
            <ul class="menu">
                <?php foreach ($clubMenu as $p => [$icon, $label]): ?>
                    <li><a class="<?= $currentPage === $p ? 'active' : '' ?>" href="<?= e(url($p)) ?>"><span class="mi"><?= $icon ?></span><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="box">
            <div class="box-head">Accesos rápidos</div>
            <ul class="menu">
                <li><a href="<?= e(url('nation', ['id' => $team['country_id']])) ?>"><span class="mi">🌎</span>Mi nación</a></li>
                <li><a href="<?= e(url('worldcup')) ?>"><span class="mi">🏅</span>Mundial</a></li>
                <li><a href="<?= e(url('transfers')) ?>"><span class="mi">🔎</span>Buscar fichajes</a></li>
                <li><a href="<?= e(url('transfers', ['tab' => 'mine'])) ?>"><span class="mi">📑</span>Mis ventas y pujas</a></li>
                <li><a href="<?= e(url('team', ['id' => $team['id']])) ?>"><span class="mi">👁</span>Vista pública</a></li>
            </ul>
        </div>
    </aside>
    <?php endif; ?>

    <main class="content">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>

    <?php if ($layout === 'game' && $team):
        $next = LeagueService::nextMatch((int)$team['id']);
        $unit = DB::row('SELECT * FROM league_units WHERE id = ?', [$team['league_unit_id']]);
        $pos = null;
        foreach (LeagueService::standings((int)$team['league_unit_id'], $season) as $row) {
            if ($row['team_id'] === (int)$team['id']) {
                $pos = $row;
            }
        }
    ?>
    <aside class="sidebar-right">
        <div class="box">
            <div class="box-head">Próximo partido</div>
            <div class="box-body">
                <?php if ($next): ?>
                    <div class="next-match">
                        <div class="nm-type"><?= e(match_type_label($next)) ?></div>
                        <div class="nm-teams"><?= e($next['home_name']) ?><br><span class="muted">vs</span><br><?= e($next['away_name']) ?></div>
                        <div class="nm-date"><?= e(fdate_long($next['scheduled_at'])) ?></div>
                        <div class="countdown" data-deadline="<?= e(date('c', strtotime($next['scheduled_at']))) ?>"><?= e(time_left($next['scheduled_at'])) ?></div>
                        <a class="btn btn-block" href="<?= e(url('lineup')) ?>">Preparar alineación</a>
                    </div>
                <?php else: ?>
                    <p class="muted">No hay partidos programados.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="box">
            <div class="box-head">Mi equipo</div>
            <table class="kv">
                <tr><th>Caja</th><td class="<?= (int)$team['cash'] < 0 ? 'neg' : '' ?>"><?= e(money($team['cash'])) ?></td></tr>
                <tr><th>Serie</th><td><a href="<?= e(url('league', ['unit' => $unit['id']])) ?>"><?= e(NationService::flag((string)DB::value('SELECT code FROM countries WHERE id = ?', [$team['country_id']]))) ?> <?= e($unit['name']) ?></a></td></tr>
                <?php if ($pos): ?><tr><th>Posición</th><td><?= $pos['pos'] ?>º (<?= $pos['pts'] ?> pts)</td></tr><?php endif; ?>
                <tr><th>Socios</th><td><?= num($team['fans']) ?></td></tr>
                <tr><th>Espíritu</th><td><?= e(mood_name($team['team_spirit'])) ?></td></tr>
                <tr><th>Confianza</th><td><?= e(mood_name($team['confidence'], 'confidence')) ?></td></tr>
            </table>
        </div>
        <div class="box">
            <div class="box-head">Hora del servidor</div>
            <div class="box-body center"><span class="clock" id="server-clock" data-ts="<?= time() * 1000 ?>"><?= date('H:i:s') ?></span><br><span class="muted small"><?= e(date_default_timezone_get()) ?></span></div>
        </div>
    </aside>
    <?php endif; ?>
</div>

<footer class="footer">
    <div class="wrap">
        <?= e($site) ?> · Juego de gestión de fútbol · v<?= APP_VERSION ?>
        <?php $le = GameState::get('last_event'); if ($le): ?><span class="muted"> · Última actualización: <?= e($le) ?></span><?php endif; ?>
    </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
