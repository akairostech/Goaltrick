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
    'home'       => ['🛡', 'Club'],
    'players'    => ['👥', 'Jugadores'],
    'lineup'     => ['📋', 'Alineación'],
    'matches'    => ['⚽', 'Partidos'],
    'league'     => ['🏆', 'Liga'],
    'cup'        => ['🥇', 'Copa'],
    'training'   => ['🏋️', 'Entrenamiento'],
    'transfers'  => ['🔁', 'Transferencias'],
    'finances'   => ['💰', 'Finanzas'],
    'sponsors'   => ['💼', 'Patrocinio'],
    'stadium'    => ['🏟', 'Estadio'],
    'staff'      => ['🧑‍💼', 'Personal'],
    'youth'      => ['🌱', 'Cantera'],
    'friendlies' => ['🤝', 'Amistosos'],
];
?><!doctype html>
<html lang="<?= e(Lang::code()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ? $pageTitle . ' · ' : '') . $site) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/logo-ball.svg')) ?>">
</head>
<body class="layout-<?= e($layout) ?>">
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="logo" href="<?= e(url()) ?>"><?= logo_html($site) ?></a>
        <div class="topbar-right">
            <?php $langs = Lang::available(); if (count($langs) > 1): $cur = Lang::code(); ?>
            <details class="lang-switch">
                <summary title="<?= e(t('Idioma')) ?>"><?= NationService::emojiFlagImg($langs[$cur][1] ?? '🌐') ?> <span class="lang-code"><?= e(strtoupper($cur)) ?></span></summary>
                <div class="lang-menu">
                    <?php foreach ($langs as $code => [$lname, $lflag]): ?>
                        <a href="<?= e(lang_url($code)) ?>" class="<?= $code === $cur ? 'active' : '' ?>" hreflang="<?= e($code) ?>"><?= NationService::emojiFlagImg($lflag) ?> <?= e($lname) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php if ($user): ?>
                <details class="tb-user user-menu">
                    <summary title="<?= e(t('Mi cuenta y mi perfil')) ?>"><?= president_img($user, 20, 'tb-face') ?> <span class="tb-uname"><?= e($user['username']) ?></span> <span class="caret">▾</span></summary>
                    <div class="user-drop">
                        <div class="user-drop-head"><?= president_img($user, 40, 'tb-face') ?><div><strong><?= e($user['username']) ?></strong><br><span class="small muted"><?= e($user['email']) ?></span></div></div>
                        <a href="<?= e(url('profile')) ?>">⚙️ <?= e(t('Mi cuenta')) ?></a>
                        <a href="<?= e(url('manager', ['id' => $user['id']])) ?>">🎩 <?= e(t('Mi perfil')) ?></a>
                        <a href="<?= e(url('manager_edit')) ?>" class="sub">✏️ <?= e(t('Editar mi perfil')) ?></a>
                    </div>
                </details>
                <a class="tb-mail<?= $unread ? ' has-unread' : '' ?>" href="<?= e(url('messages')) ?>" title="<?= e(t('Mensajes')) ?>">✉️<?php if ($unread): ?><span class="badge"><?= $unread ?></span><?php endif; ?></a>
                <span class="tb-season"><?= e(t('Temporada {s} · Semana {w}', ['s' => $season, 'w' => $week])) ?></span>
                <a class="btn btn-small btn-ghost" href="<?= e(url('logout', ['t' => csrf_token()])) ?>"><?= e(t('Salir')) ?></a>
            <?php else: ?>
                <a class="btn btn-small btn-ghost" href="<?= e(url('login')) ?>"><?= e(t('Iniciar sesión')) ?></a>
                <a class="btn btn-small btn-accent" href="<?= e(url('register')) ?>"><?= e(t('Regístrate gratis')) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($user): ?>
    <nav class="mainnav">
        <div class="wrap">
            <a class="<?= $navActive === 'club' ? 'active' : '' ?>" href="<?= e(url()) ?>"><?= e(t('Mi club')) ?></a>
            <a class="<?= $navActive === 'league' ? 'active' : '' ?>" href="<?= e(url('league')) ?>"><?= e(t('Liga')) ?></a>
            <a class="<?= $navActive === 'transfers' ? 'active' : '' ?>" href="<?= e(url('transfers')) ?>"><?= e(t('Transferencias')) ?></a>
            <a class="<?= $navActive === 'nations' ? 'active' : '' ?>" href="<?= e(url('nations')) ?>"><?= e(t('Naciones')) ?></a>
            <a class="<?= $navActive === 'worldcup' ? 'active' : '' ?>" href="<?= e(url('worldcup')) ?>"><?= e(t('Mundial')) ?></a>
            <a class="<?= $navActive === 'world' ? 'active' : '' ?>" href="<?= e(url('world')) ?>"><?= e(t('Mundo')) ?></a>
            <a class="<?= $navActive === 'community' ? 'active' : '' ?>" href="<?= e(url('messages')) ?>"><?= e(t('Comunidad')) ?><?php if ($unread): ?> <span class="badge"><?= $unread ?></span><?php endif; ?></a>
            <a class="<?= $navActive === 'help' ? 'active' : '' ?>" href="<?= e(url('help')) ?>"><?= e(t('Ayuda')) ?></a>
            <?php if ((int)$user['is_admin']): ?>
                <?php $openTickets = Moderation::openTicketsCount(); ?>
                <a class="nav-admin <?= $navActive === 'admin' ? 'active' : '' ?>" href="<?= e(url('admin')) ?>"><?= e(t('Administración')) ?><?php if ($openTickets): ?> <span class="badge" title="<?= e(t('Tickets de ayuda sin atender')) ?>"><?= $openTickets ?></span><?php endif; ?></a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</header>

<div class="wrap page">
    <?php if ($layout === 'game' && $team): ?>
    <aside class="sidebar-left">
        <div class="box">
            <div class="box-head side-team"><?= crest_img($team, 20) ?> <?= e($team['name']) ?></div>
            <ul class="menu">
                <?php foreach ($clubMenu as $p => [$icon, $label]): ?>
                    <li><a class="<?= ($currentPage === $p || ($p === 'home' && $currentPage === 'club')) ? 'active' : '' ?>" href="<?= e(url($p)) ?>"><span class="mi"><?= $icon ?></span><?= e(t($label)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="box">
            <div class="box-head"><?= e(t('Accesos rápidos')) ?></div>
            <ul class="menu">
                <li><a href="<?= e(url('nation', ['id' => $team['country_id']])) ?>"><span class="mi">🌎</span><?= e(t('Mi nación')) ?></a></li>
                <li><a href="<?= e(url('worldcup')) ?>"><span class="mi">🏅</span><?= e(t('Mundial')) ?></a></li>
                <li><a href="<?= e(url('team', ['id' => $team['id']])) ?>"><span class="mi">👁</span><?= e(t('Vista pública')) ?></a></li>
                <?php $supportUnread = Moderation::unreadForUser((int)$user['id']); ?>
                <li><a class="<?= $currentPage === 'support' ? 'active' : '' ?>" href="<?= e(url('support')) ?>"><span class="mi">🆘</span><?= e(t('Ayuda y soporte')) ?><?php if ($supportUnread): ?> <span class="badge"><?= $supportUnread ?></span><?php endif; ?></a></li>
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
            <div class="box-head"><?= e(t('Próximo partido')) ?></div>
            <div class="box-body">
                <?php if ($next): ?>
                    <div class="next-match">
                        <div class="nm-type"><?= e(match_type_label($next)) ?></div>
                        <div class="nm-teams"><?= e($next['home_name']) ?><br><span class="muted"><?= e(t('vs')) ?></span><br><?= e($next['away_name']) ?></div>
                        <div class="nm-date"><?= e(fdate_long($next['scheduled_at'])) ?></div>
                        <div class="countdown" data-deadline="<?= e(date('c', strtotime($next['scheduled_at']))) ?>"><?= e(time_left($next['scheduled_at'])) ?></div>
                        <a class="btn btn-block" href="<?= e(url('lineup')) ?>"><?= e(t('Preparar alineación')) ?></a>
                    </div>
                <?php else: ?>
                    <p class="muted"><?= e(t('No hay partidos programados.')) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="box">
            <div class="box-head"><?= e(t('Mi equipo')) ?></div>
            <table class="kv">
                <tr><th><?= e(t('Caja')) ?></th><td class="<?= (int)$team['cash'] < 0 ? 'neg' : '' ?>"><?= e(money($team['cash'])) ?></td></tr>
                <tr><th><?= e(t('División')) ?></th><td><a href="<?= e(url('league', ['unit' => $unit['id']])) ?>"><?= flag((string)DB::value('SELECT code FROM countries WHERE id = ?', [$team['country_id']])) ?> <?= e($unit['name']) ?></a></td></tr>
                <?php if ($pos): ?><tr><th><?= e(t('Posición')) ?></th><td><?= e(t('{pos}º ({pts} pts)', ['pos' => $pos['pos'], 'pts' => $pos['pts']])) ?></td></tr><?php endif; ?>
                <tr><th><?= e(t('Socios')) ?></th><td><?= num($team['fans']) ?></td></tr>
                <tr><th><?= e(t('Espíritu')) ?></th><td><?= e(mood_name($team['team_spirit'])) ?></td></tr>
                <tr><th><?= e(t('Confianza')) ?></th><td><?= e(mood_name($team['confidence'], 'confidence')) ?></td></tr>
            </table>
        </div>
        <div class="box">
            <div class="box-head"><?= e(t('Hora del servidor')) ?></div>
            <div class="box-body center"><span class="clock" id="server-clock" data-ts="<?= time() * 1000 ?>"><?= date('H:i:s') ?></span><br><span class="muted small"><?= e(date_default_timezone_get()) ?></span></div>
        </div>
    </aside>
    <?php endif; ?>
</div>

<footer class="footer">
    <div class="wrap">
        <?= e($site) ?> · <?= e(t('Juego de gestión de fútbol')) ?> · v<?= APP_VERSION ?>
        <?php $le = GameState::get('last_event'); if ($le): ?><span class="muted"> · <?= e(t('Última actualización: {x}', ['x' => tdb($le)])) ?></span><?php endif; ?>
    </div>
</footer>
<script>window.GT_I18N = <?= json_encode(Lang::code() === 'es' ? new stdClass() : Lang::jsDictionary(JS_STRINGS), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
