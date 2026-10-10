<?php
require_login();
$pageTitle = 'Naciones';
$navActive = 'nations';
$my = current_team();
$titles = WorldCupService::titles();
$rows = DB::all(
    'SELECT c.*,
        (SELECT MAX(level) FROM league_units lu WHERE lu.country_id = c.id) AS divisions,
        (SELECT COUNT(*) FROM league_units lu WHERE lu.country_id = c.id) AS series,
        (SELECT COUNT(*) FROM teams t WHERE t.country_id = c.id AND t.is_national = 0) AS teams,
        (SELECT COUNT(*) FROM teams t WHERE t.country_id = c.id AND t.is_national = 0 AND t.user_id IS NOT NULL) AS managers,
        (SELECT COUNT(*) FROM players p WHERE p.country_id = c.id AND p.retired = 0 AND p.team_id IS NOT NULL) AS players,
        (SELECT id FROM teams t WHERE t.country_id = c.id AND t.is_national = 1 LIMIT 1) AS national_id,
        (SELECT id FROM league_units lu WHERE lu.country_id = c.id AND lu.level = 1 LIMIT 1) AS top_unit
     FROM countries c ORDER BY c.name'
);
foreach ($rows as &$r) {
    $r['strength'] = NationService::strength((int)$r['id']);
}
unset($r);
$ranking = $rows;
usort($ranking, fn($a, $b) => $b['strength'] <=> $a['strength']);
$rank = [];
foreach ($ranking as $i => $r) {
    $rank[(int)$r['id']] = $i + 1;
}
?>
<div class="page-title">
    <h1>Naciones</h1>
    <span class="muted"><?= count($rows) ?> países con liga propia y selección nacional</span>
</div>
<p class="muted">Cada país tiene su propia pirámide de ligas (series de 8 equipos con ascensos y descensos) y una selección nacional formada por los jugadores de esa nacionalidad con mayor TSI en cada posición, jueguen donde jueguen. Las selecciones compiten en el <a href="<?= e(url('worldcup')) ?>">Mundial</a>.</p>

<div class="box">
    <div class="box-head">Países</div>
    <div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>País</th><th>Liga</th><th class="num">Div.</th><th class="num">Series</th><th class="num">Equipos</th>
            <th class="num">Mánag.</th><th class="num">Jugadores</th><th class="num" title="Suma del TSI de los convocados">Fuerza</th><th class="num">Rank.</th><th class="num" title="Mundiales ganados">Títulos</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr<?= $my && (int)$my['country_id'] === (int)$r['id'] ? ' class="me"' : '' ?>>
                <td class="nowrap"><?= nation_link($r['id'], $r['name'], $r['code']) ?></td>
                <td class="nowrap"><?= $r['top_unit'] ? '<a href="' . e(url('league', ['unit' => $r['top_unit']])) . '">Serie I</a>' : '<span class="muted">—</span>' ?></td>
                <td class="num"><?= (int)$r['divisions'] ?></td>
                <td class="num"><?= (int)$r['series'] ?></td>
                <td class="num"><?= num($r['teams']) ?></td>
                <td class="num"><?= num($r['managers']) ?></td>
                <td class="num"><?= num($r['players']) ?></td>
                <td class="num"><?= num($r['strength']) ?></td>
                <td class="num"><?= $rank[(int)$r['id']] ?>º</td>
                <td class="num"><?= isset($titles[(int)$r['national_id']]) ? str_repeat('🏆', $titles[(int)$r['national_id']]) : '<span class="muted">0</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
