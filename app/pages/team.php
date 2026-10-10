<?php
require_login();
$my = current_team();
$id = (int)get('id');
// Las selecciones nacionales tienen su propia página
$nat = DB::row('SELECT is_national, country_id FROM teams WHERE id = ?', [$id]);
if ($nat && (int)$nat['is_national']) {
    redirect(url('nation', ['id' => $nat['country_id']]));
}
$t = DB::row('SELECT t.*, u.username, u.id AS uid, u.last_activity_at, lu.name AS unit_name, c.name AS country, c.code AS ccode
    FROM teams t LEFT JOIN users u ON u.id = t.user_id JOIN league_units lu ON lu.id = t.league_unit_id JOIN countries c ON c.id = t.country_id WHERE t.id = ?', [$id]);
if (!$t) {
    flash('error', t('Equipo no encontrado.'));
    redirect(url('world'));
}
$pageTitle = $t['name'];
$navActive = 'world';
$players = DB::all("SELECT p.*, (SELECT l.id FROM transfer_listings l WHERE l.player_id = p.id AND l.status = 'active' LIMIT 1) AS listing_id
    FROM players p WHERE p.team_id = ? AND p.retired = 0 ORDER BY FIELD(role,'GK','CD','WB','IM','W','FW'), tsi DESC", [$id]);
$recent = LeagueService::teamMatches($id, "status = 'played'", [], 'x.played_at DESC', 6);
$history = DB::all('SELECT sh.*, lu.name FROM season_history sh JOIN league_units lu ON lu.id = sh.league_unit_id WHERE sh.team_id = ? ORDER BY season DESC', [$id]);
$capacity = (int)$t['seats_terraces'] + (int)$t['seats_basic'] + (int)$t['seats_roof'] + (int)$t['seats_vip'];
$resultNames = ['champion' => '🏆 ' . t('Campeón'), 'promoted' => '⬆️ ' . t('Ascenso'), 'relegated' => '⬇️ ' . t('Descenso'), 'stay' => ''];
?>
<div class="page-title">
    <h1 class="club-h1"><?= crest_img($t, 36) ?> <?= e($t['name']) ?></h1>
    <span>
        <?php if ($my && (int)$my['id'] !== $id): ?>
            <a class="btn btn-small" href="<?= e(url('friendlies', ['to' => $id])) ?>">🤝 <?= e(t('Invitar a amistoso')) ?></a>
            <?php if ($t['uid']): ?><a class="btn btn-small btn-grey" href="<?= e(url('messages', ['compose' => 1, 'to' => $t['username']])) ?>">✉️ <?= e(t('Mensaje')) ?></a><?php endif; ?>
        <?php endif; ?>
    </span>
</div>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Información del club')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Mánager')) ?></th><td><?= $t['username'] ? e($t['username']) : '<span class="tag tag-bot">' . e(t('Controlado por la máquina')) . '</span>' ?></td></tr>
            <?php if ($t['username']): ?><tr><th><?= e(t('Última conexión')) ?></th><td><?= e(fdate($t['last_activity_at'])) ?></td></tr><?php endif; ?>
            <tr><th><?= e(t('Liga')) ?></th><td><?= nation_link($t['country_id'], $t['country'], $t['ccode']) ?> · <a href="<?= e(url('league', ['unit' => $t['league_unit_id']])) ?>"><?= e(t('División {name}', ['name' => $t['unit_name']])) ?></a></td></tr>
            <tr><th><?= e(t('Escudo y uniforme')) ?></th><td class="club-art-cell"><?= crest_img($t, 52, 'crest') ?> <?= kit_img($t, 56) ?></td></tr>
            <tr><th><?= e(t('Fundado')) ?></th><td><?= e(fdate($t['founded_at'], false)) ?></td></tr>
            <tr><th><?= e(t('Estadio')) ?></th><td><?= e($t['stadium_name']) ?> (<?= num($capacity) ?>)</td></tr>
            <tr><th><?= e(t('Socios')) ?></th><td><?= num($t['fans']) ?></td></tr>
            <?php $tr = StaffService::trainer($id); if ($tr): ?><tr><th><?= e(t('Entrenador')) ?></th><td><img class="avatar" src="<?= e(StaffService::avatarUrl($tr)) ?>" width="22" height="22" alt=""> <?= StaffService::link($tr) ?> <span class="small muted">(<?= e(t('nivel {n}', ['n' => (int)$tr['level']])) ?>, <?= e(t(StaffService::PERSONALITIES[$tr['personality']][0])) ?>)</span></td></tr><?php endif; ?>
            <?php $sp = SponsorService::activeContract($id); if ($sp): ?><tr><th><?= e(t('Patrocinador')) ?></th><td><img src="<?= e(SponsorService::logoUrl((int)$sp['company_id'])) ?>" width="22" height="22" class="avatar" alt=""> <?= e($sp['company']) ?> <span class="small muted">(<?= e(t($sp['sector'])) ?>)</span></td></tr><?php endif; ?>
            <tr><th><?= e(t('Jugadores')) ?></th><td><?= e(t('{n} · TSI total {tsi}', ['n' => count($players), 'tsi' => num(array_sum(array_column($players, 'tsi')))])) ?></td></tr>
        </table>
    </div>
    <div class="box">
        <div class="box-head">🏟 <?= e(t('Estadio')) ?></div>
        <div class="stadium-mini" style="margin:0"><?= StadiumArt::svg(['terraces' => $t['seats_terraces'], 'basic' => $t['seats_basic'], 'roof' => $t['seats_roof'], 'vip' => $t['seats_vip']], $id, 0.6, (bool)$t['build_finishes_at'], $t['stadium_name']) ?></div>
        <div class="box-body small center"><strong><?= e($t['stadium_name']) ?></strong> · <?= e(StadiumArt::modelName($capacity)) ?> · <?= num($capacity) ?></div>
    </div>
</div>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Últimos partidos')) ?> <a href="<?= e(url('matches', ['team' => $id])) ?>"><?= e(t('Todos')) ?> »</a></div>
        <table class="table compact">
            <?php foreach ($recent as $m): ?>
                <tr><td class="small nowrap"><?= e(fdate($m['played_at'], false)) ?></td><td><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><?= e($m['hn']) ?> <strong><?= (int)$m['home_goals'] ?>-<?= (int)$m['away_goals'] ?></strong> <?= e($m['an']) ?></a></td></tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td class="muted"><?= e(t('Sin partidos.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
</div>
<br>
<?= trophy_cabinet($id) ?>
<div class="box">
    <div class="box-head"><?= e(t('Plantilla')) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th><?= e(t('Jugador')) ?></th><th><?= e(t('Pos')) ?></th><th><?= e(t('Edad')) ?></th><th class="num">TSI</th><th class="num" title="<?= e(t('Forma')) ?>"><?= e(t('For')) ?></th><th class="num" title="<?= e(t('Portería')) ?>"><?= e(t('Por')) ?></th><th class="num" title="<?= e(t('Defensa')) ?>"><?= e(t('Def')) ?></th><th class="num" title="<?= e(t('Jugadas')) ?>"><?= e(t('Jug')) ?></th><th class="num" title="<?= e(t('Lateral')) ?>"><?= e(t('Lat')) ?></th><th class="num" title="<?= e(t('Pases')) ?>"><?= e(t('Pas')) ?></th><th class="num" title="<?= e(t('Anotación')) ?>"><?= e(t('Ano')) ?></th><th class="num" title="<?= e(t('Balón parado')) ?>"><?= e(t('BP')) ?></th><th><?= e(t('Esp.')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($players as $p): ?>
            <tr>
                <td class="nowrap"><?= avatar_img($p, 22) ?> <?= player_link($p) ?></td>
                <td><span class="tag"><?= e($p['role']) ?></span></td>
                <td><?= (int)$p['age_years'] ?></td>
                <td class="num"><?= num($p['tsi']) ?></td>
                <td class="num"><?= (int)floor((float)$p['form']) ?></td>
                <?php foreach (['keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring', 'set_pieces'] as $sk): ?>
                    <td class="num" title="<?= e(skill_label($p[$sk])) ?>"><?= (int)floor((float)$p[$sk]) ?></td>
                <?php endforeach; ?>
                <td><?= $p['specialty'] ? SPECIALTIES[$p['specialty']][1] : '' ?></td>
                <td><?= $p['listing_id'] ? '<span class="tag tag-sale">' . e(t('En venta')) . '</span>' : '' ?><?= (float)$p['injury_weeks'] > 0.5 ? ' <span class="tag tag-injury">+' . ceil((float)$p['injury_weeks']) . '</span>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php if ($history): ?>
<div class="box">
    <div class="box-head"><?= e(t('Historial de temporadas')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Temporada')) ?></th><th><?= e(t('División')) ?></th><th class="num"><?= e(t('Posición')) ?></th><th class="num"><?= e(t('Puntos')) ?></th><th class="num"><?= e(t('Goles')) ?></th><th></th></tr></thead>
        <?php foreach ($history as $h): ?>
            <tr><td><?= (int)$h['season'] ?></td><td><?= e($h['name']) ?></td><td class="num"><?= (int)$h['position'] ?>º</td><td class="num"><?= (int)$h['points'] ?></td><td class="num"><?= (int)$h['goals_for'] ?>:<?= (int)$h['goals_against'] ?></td><td><?= e($resultNames[$h['result']] ?? '') ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
