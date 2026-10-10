<?php
require_login();
$pageTitle = t('Mundo');
$navActive = 'world';
$q = trim((string)get('q'));
$results = [];
if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    $results = DB::all('SELECT t.id, t.name, lu.name AS unit, u.username, u.id AS uid FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id
        LEFT JOIN users u ON u.id = t.user_id WHERE t.name LIKE ? OR u.username LIKE ? ORDER BY t.name LIMIT 50', [$like, $like]);
}
$countryList = DB::all('SELECT * FROM countries ORDER BY name');
$online = DB::all('SELECT u.id AS uid, u.username, t.id AS tid, t.name FROM users u LEFT JOIN teams t ON t.user_id = u.id WHERE u.last_activity_at > ? ORDER BY u.username LIMIT 50', [date('Y-m-d H:i:s', time() - 900)]);
$rankTsi = DB::all('SELECT t.id, t.name, t.is_bot, t.crest_id, t.crest_custom, SUM(p.tsi) s FROM teams t JOIN players p ON p.team_id = t.id AND p.retired = 0 GROUP BY t.id, t.name, t.is_bot, t.crest_id, t.crest_custom ORDER BY s DESC LIMIT 10');

$rankFans = DB::all('SELECT id, name, is_bot, fans, crest_id, crest_custom FROM teams WHERE is_national = 0 ORDER BY fans DESC LIMIT 10');
$stats = DB::row('SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM teams WHERE user_id IS NOT NULL) h, (SELECT COUNT(*) FROM teams WHERE is_national = 0) t, (SELECT COUNT(*) FROM players WHERE retired = 0) p');
?>
<div class="page-title"><h1><?= e(t('Mundo')) ?></h1><span class="muted"><?= e(t('{managers} mánagers · {teams} equipos · {players} jugadores', ['managers' => num($stats['h']), 'teams' => num($stats['t']), 'players' => num($stats['p'])])) ?></span></div>

<div class="box"><div class="box-head"><?= e(t('Buscar equipos o mánagers')) ?></div><div class="box-body">
    <form method="get" class="inline-form"><input type="hidden" name="page" value="world"><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Nombre del equipo o usuario')) ?>" style="min-width:260px"><button class="btn" type="submit"><?= e(t('Buscar')) ?></button></form>
    <?php if ($q !== ''): ?>
        <table class="table compact" style="margin-top:10px">
            <?php foreach ($results as $r): ?><tr><td><?= team_link($r['id'], $r['name']) ?></td><td><?= manager_link($r['uid'], $r['username'], true) ?></td><td><?= e(t('División {name}', ['name' => $r['unit']])) ?></td></tr><?php endforeach; ?>
            <?php if (!$results): ?><tr><td class="muted"><?= e(t('Sin resultados.')) ?></td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
</div></div>

<div class="box"><div class="box-head"><?= e(t('Ligas del mundo')) ?></div><div class="box-body">
    <p><?= t('Consulta también las {link} del mundo.', ['link' => '<a href="' . e(url('companies')) . '">' . e(t('400 empresas patrocinadoras')) . '</a>']) ?></p>
    <p><?= t('Hay {n} países con liga propia. Consulta su estructura, su selección nacional y el Mundial en {link}.', ['n' => count($countryList), 'link' => '<a href="' . e(url('nations')) . '">' . e(t('Naciones')) . '</a>']) ?></p>
    <p><?php foreach ($countryList as $cl): ?><span class="nowrap"><?= nation_link($cl['id'], $cl['name'], $cl['code']) ?></span> &nbsp; <?php endforeach; ?></p>
</div></div>

<div class="grid-3">
    <div class="box"><div class="box-head"><?= e(t('Conectados ({n})', ['n' => count($online)])) ?></div>
        <table class="table compact"><?php foreach ($online as $o): ?><tr><td><?= manager_link($o['uid'], $o['username'], true) ?></td><td class="small"><?= $o['tid'] ? team_link($o['tid'], $o['name']) : '' ?></td></tr><?php endforeach; ?></table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Ranking por TSI')) ?></div>
        <table class="table compact"><?php foreach ($rankTsi as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><span class="crest-name"><?= crest_img($r, 20) ?> <?= team_link($r['id'], $r['name']) ?></span></td><td class="num"><?= num($r['s']) ?></td></tr><?php endforeach; ?></table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Ranking por socios')) ?></div>
        <table class="table compact"><?php foreach ($rankFans as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><span class="crest-name"><?= crest_img($r, 20) ?> <?= team_link($r['id'], $r['name']) ?></span></td><td class="num"><?= num($r['fans']) ?></td></tr><?php endforeach; ?></table>
    </div>
</div>
