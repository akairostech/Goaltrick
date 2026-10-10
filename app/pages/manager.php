<?php
/** Perfil público de un mánager (v1.10) */
$me = require_login();
$id = (int)(get('id') ?: $me['id']);
$u = DB::row('SELECT u.*, c.name AS country, c.code AS ccode FROM users u LEFT JOIN countries c ON c.id = u.country_id WHERE u.id = ?', [$id]);
if (!$u) {
    flash('error', t('Mánager no encontrado.'));
    redirect(url('world'));
}
$own = (int)$me['id'] === $id;
$isAdmin = (int)($me['is_admin'] ?? 0) === 1;
$pageTitle = t('Perfil de {user}', ['user' => $u['username']]);
$navActive = $own ? 'club' : 'world';

// Visitas: una por sesión y perfil, sin contar las del propio mánager
if (!$own && empty($_SESSION['pv'][$id])) {
    $_SESSION['pv'][$id] = 1;
    DB::exec('UPDATE users SET profile_views = profile_views + 1 WHERE id = ?', [$id]);
    $u['profile_views'] = (int)$u['profile_views'] + 1;
}

$team = DB::row('SELECT t.*, lu.name AS unit_name, lu.level, c.name AS tcountry, c.code AS tcode FROM teams t
    LEFT JOIN league_units lu ON lu.id = t.league_unit_id JOIN countries c ON c.id = t.country_id
    WHERE t.user_id = ? AND t.is_national = 0', [$id]);

$online = $u['last_activity_at'] && strtotime($u['last_activity_at']) > time() - 900;
$age = $u['birth_year'] ? (int)date('Y') - (int)$u['birth_year'] : null;

$stats = null;
if ($team) {
    $tid = (int)$team['id'];
    $since = $team['founded_at'];
    $played = LeagueService::teamMatches($tid, "status = 'played' AND played_at >= ?", [$since], 'x.played_at DESC', 2000);
    $stats = ['pj' => 0, 'g' => 0, 'e' => 0, 'p' => 0, 'gf' => 0, 'gc' => 0, 'league' => 0, 'big' => null];
    foreach ($played as $m) {
        $home = (int)$m['home_team_id'] === $tid;
        $for = (int)($home ? $m['home_goals'] : $m['away_goals']);
        $ag = (int)($home ? $m['away_goals'] : $m['home_goals']);
        $stats['pj']++;
        $stats['gf'] += $for;
        $stats['gc'] += $ag;
        $stats[$for > $ag ? 'g' : ($for === $ag ? 'e' : 'p')]++;
        if ($m['type'] === 'league') {
            $stats['league']++;
        }
        if ($for > $ag && (!$stats['big'] || $for - $ag > $stats['big'][0])) {
            $stats['big'] = [$for - $ag, $m];
        }
    }
    $recent = array_slice($played, 0, 6);
    $history = DB::all('SELECT sh.*, lu.name, lu.level FROM season_history sh JOIN league_units lu ON lu.id = sh.league_unit_id WHERE sh.team_id = ? ORDER BY sh.season DESC', [$tid]);
    $titles = count(array_filter($history, fn($h) => (int)$h['position'] === 1));
    $cups = (int)DB::value('SELECT COUNT(*) FROM league_cups WHERE winner_team_id = ?', [$tid]);
    $bought = DB::row("SELECT COUNT(*) n, COALESCE(SUM(highest_bid), 0) s FROM transfer_listings WHERE status = 'sold' AND highest_bidder_id = ? AND closed_at >= ?", [$tid, $since]);
    $sold = DB::row("SELECT COUNT(*) n, COALESCE(SUM(highest_bid), 0) s FROM transfer_listings WHERE status = 'sold' AND seller_team_id = ? AND closed_at >= ?", [$tid, $since]);
    $bestPos = null;
    foreach ($history as $h) {
        if ($bestPos === null || [(int)$h['level'], (int)$h['position']] < [(int)$bestPos['level'], (int)$bestPos['position']]) {
            $bestPos = $h;
        }
    }
    $daysInCharge = max(0, (int)floor((time() - strtotime($since)) / 86400));
}
$resultNames = ['champion' => '🏆 ' . t('Campeón'), 'promoted' => '⬆️ ' . t('Ascenso'), 'relegated' => '⬇️ ' . t('Descenso'), 'stay' => ''];
?>
<div class="mgr-hero">
    <div class="mgr-face"><?= president_img($u, 150, 'president-big') ?><?php if ($online): ?><span class="mgr-online" title="<?= e(t('Conectado ahora')) ?>"></span><?php endif; ?></div>
    <div class="mgr-main">
        <h1><?= e($u['username']) ?>
            <?php if ((int)$u['is_admin']): ?><span class="tag tag-admin">🛡 <?= e(t('Administrador')) ?></span><?php endif; ?>
            <?php if ((int)$u['is_banned']): ?><span class="tag tag-banned"><?= e(t('Cuenta sancionada')) ?></span><?php endif; ?>
        </h1>
        <?php if ($u['motto']): ?><p class="mgr-motto">“<?= e($u['motto']) ?>”</p><?php endif; ?>
        <ul class="mgr-facts">
            <li><?= e($team ? t('Presidente de {team}', ['team' => $team['name']]) : t('Sin club')) ?></li>
            <?php if ($u['country']): ?><li><?= flag($u['ccode']) ?> <?= e(t($u['country'])) ?><?= $u['city'] ? ' · ' . e($u['city']) : '' ?></li><?php elseif ($u['city']): ?><li>📍 <?= e($u['city']) ?></li><?php endif; ?>
            <?php if ($age): ?><li>🎂 <?= e(t('{n} años', ['n' => $age])) ?></li><?php endif; ?>
            <?php if ($u['fav_club']): ?><li>❤️ <?= e(t('Club favorito: {club}', ['club' => $u['fav_club']])) ?></li><?php endif; ?>
        </ul>
        <div class="mgr-meta small">
            <span>📅 <?= e(t('Miembro desde {d}', ['d' => fdate($u['created_at'], false)])) ?></span>
            <span><?= $online ? '🟢 ' . e(t('Conectado ahora')) : '🕒 ' . e(t('Última conexión: {d}', ['d' => fdate($u['last_activity_at'])])) ?></span>
            <span>👁 <?= e((int)$u['profile_views'] === 1 ? t('1 visita al perfil') : t('{n} visitas al perfil', ['n' => num($u['profile_views'])])) ?></span>
        </div>
        <div class="mgr-actions">
            <?php if ($own): ?>
                <a class="btn" href="<?= e(url('manager_edit')) ?>">✏️ <?= e(t('Editar mi perfil')) ?></a>
                <a class="btn btn-grey" href="<?= e(url('profile')) ?>">⚙️ <?= e(t('Mi cuenta')) ?></a>
            <?php else: ?>
                <a class="btn btn-small" href="<?= e(url('messages', ['compose' => 1, 'to' => $u['username']])) ?>">✉️ <?= e(t('Mensaje')) ?></a>
                <?php $myTeam = current_team(); if ($team && $myTeam): ?><a class="btn btn-small btn-grey" href="<?= e(url('friendlies', ['to' => $team['id']])) ?>">🤝 <?= e(t('Invitar a amistoso')) ?></a><?php endif; ?>
                <a class="btn btn-small btn-grey" href="<?= e(url('support', ['new' => 1, 'report' => $u['username'], 'link' => 'index.php?page=manager&id=' . $id])) ?>" title="<?= e(t('Reportar a este mánager a la administración')) ?>">🚩 <?= e(t('Reportar')) ?></a>
            <?php endif; ?>
            <?php if ($isAdmin && !$own): ?><a class="btn btn-small btn-danger" href="<?= e(url('admin_user', ['id' => $id])) ?>">🔎 <?= e(t('Investigar')) ?></a><?php endif; ?>
        </div>
    </div>
    <?php if ($team): ?>
    <a class="mgr-club" href="<?= e(url('team', ['id' => $team['id']])) ?>" title="<?= e(t('Ver el club')) ?>">
        <?= crest_img($team, 96, 'crest crest-big') ?>
        <strong><?= e($team['name']) ?></strong>
        <span class="small"><?= flag($team['tcode']) ?> <?= e(t('División {name}', ['name' => $team['unit_name']])) ?></span>
    </a>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head">📝 <?= e(t('Sobre mí')) ?></div>
        <div class="box-body mgr-bio">
            <?php if (trim((string)$u['bio']) !== ''): ?>
                <?= nl2br(e($u['bio'])) ?>
            <?php else: ?>
                <p class="muted"><?= e($own ? t('Todavía no has escrito tu presentación. Cuéntale al mundo quién eres desde «Editar mi perfil».') : t('Este mánager todavía no ha escrito su presentación.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="box">
        <div class="box-head">📊 <?= e(t('Trayectoria al frente del club')) ?></div>
        <?php if ($stats): ?>
        <div class="mgr-stats">
            <div><strong><?= num($daysInCharge) ?></strong><span><?= e(t('días en el cargo')) ?></span></div>
            <div><strong><?= num($stats['pj']) ?></strong><span><?= e(t('partidos')) ?></span></div>
            <div><strong><?= $stats['pj'] ? num(100 * $stats['g'] / $stats['pj']) . '%' : '—' ?></strong><span><?= e(t('victorias')) ?></span></div>
            <div><strong><?= num($titles + $cups) ?></strong><span><?= e(t('títulos')) ?></span></div>
        </div>
        <table class="kv">
            <tr><th><?= e(t('Balance')) ?></th><td><span class="form-badge form-V"><?= e(t('V##racha')) ?></span> <?= num($stats['g']) ?> &nbsp; <span class="form-badge form-E"><?= e(t('E##racha')) ?></span> <?= num($stats['e']) ?> &nbsp; <span class="form-badge form-D"><?= e(t('D##racha')) ?></span> <?= num($stats['p']) ?></td></tr>
            <tr><th><?= e(t('Goles')) ?></th><td><?= e(t('{f} a favor · {a} en contra', ['f' => num($stats['gf']), 'a' => num($stats['gc'])])) ?></td></tr>
            <?php if ($stats['big']): [$diff, $bm] = $stats['big']; ?>
            <tr><th><?= e(t('Mayor goleada')) ?></th><td><a href="<?= e(url('match', ['id' => $bm['id']])) ?>"><?= e($bm['hn']) ?> <?= (int)$bm['home_goals'] ?> - <?= (int)$bm['away_goals'] ?> <?= e($bm['an']) ?></a></td></tr>
            <?php endif; ?>
            <tr><th><?= e(t('Ligas y copas')) ?></th><td><?= e(t('{l} liga(s) · {c} copa(s)', ['l' => $titles, 'c' => $cups])) ?></td></tr>
            <?php if ($bestPos): ?><tr><th><?= e(t('Mejor temporada')) ?></th><td><?= e(t('{p}º en la División {name} (temp. {s})', ['p' => (int)$bestPos['position'], 'name' => $bestPos['name'], 's' => (int)$bestPos['season']])) ?></td></tr><?php endif; ?>
            <tr><th><?= e(t('Fichajes')) ?></th><td><?= e(t('{n} comprados por {m}', ['n' => (int)$bought['n'], 'm' => money($bought['s'])])) ?></td></tr>
            <tr><th><?= e(t('Ventas')) ?></th><td><?= e(t('{n} vendidos por {m}', ['n' => (int)$sold['n'], 'm' => money($sold['s'])])) ?></td></tr>
        </table>
        <?php else: ?>
            <div class="box-body muted"><?= e(t('Este mánager no dirige ningún club en este momento.')) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($team): ?>
<?= trophy_cabinet((int)$team['id'], $own) ?>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Últimos partidos')) ?></div>
        <table class="table compact">
            <?php foreach ($recent as $m): ?>
                <tr>
                    <td class="small muted nowrap"><?= e(fdate($m['played_at'], false)) ?></td>
                    <td class="right"><?= e($m['hn']) ?> <?= crest_img(['id' => $m['home_team_id'], 'crest_id' => $m['h_crest_id'], 'crest_custom' => $m['h_crest_custom']], 18) ?></td>
                    <td class="center nowrap"><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><strong><?= (int)$m['home_goals'] ?> - <?= (int)$m['away_goals'] ?></strong></a></td>
                    <td><?= crest_img(['id' => $m['away_team_id'], 'crest_id' => $m['a_crest_id'], 'crest_custom' => $m['a_crest_custom']], 18) ?> <?= e($m['an']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td class="muted"><?= e(t('Todavía no ha jugado ningún partido.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Historial de temporadas')) ?></div>
        <table class="table compact">
            <thead><tr><th><?= e(t('Temporada')) ?></th><th><?= e(t('División')) ?></th><th class="num"><?= e(t('Pos.')) ?></th><th class="num"><?= e(t('Pts')) ?></th><th></th></tr></thead>
            <?php foreach ($history as $h): ?>
                <tr><td><?= (int)$h['season'] ?></td><td><a href="<?= e(url('league', ['unit' => $h['league_unit_id'], 'season' => $h['season']])) ?>"><?= e($h['name']) ?></a></td><td class="num"><?= (int)$h['position'] ?>º</td><td class="num"><?= (int)$h['points'] ?></td><td class="small"><?= e($resultNames[$h['result']] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="5" class="muted"><?= e(t('Todavía no ha terminado ninguna temporada.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>
