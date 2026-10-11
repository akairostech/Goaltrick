<?php
$team = require_team();
$pageTitle = t('Jugadores');
$navActive = 'club';

$sorts = [
    'name' => 'last_name ASC', 'age' => 'age_years ASC, age_days ASC', 'tsi' => 'tsi DESC', 'form' => 'form DESC',
    'keeper' => 'keeper DESC', 'defending' => 'defending DESC', 'playmaking' => 'playmaking DESC', 'winger' => 'winger DESC',
    'passing' => 'passing DESC', 'scoring' => 'scoring DESC', 'set_pieces' => 'set_pieces DESC', 'stamina' => 'stamina DESC',
    'wage' => 'wage DESC', 'role' => "FIELD(role,'GK','CD','WB','IM','W','FW'), tsi DESC", 'xp' => 'experience DESC',
];
$sort = (string)get('sort', 'role');
$order = $sorts[$sort] ?? $sorts['role'];
$players = DB::all("SELECT p.*, c.code AS ccode, c.name AS cname,
        (SELECT l.id FROM transfer_listings l WHERE l.player_id = p.id AND l.status = 'active' LIMIT 1) AS listing_id
    FROM players p JOIN countries c ON c.id = p.country_id WHERE p.team_id = ? AND p.retired = 0 ORDER BY $order", [$team['id']]);
$wages = array_sum(array_column($players, 'wage'));
// v1.10.6: estadísticas de la plantilla (totales y promedios por jugador)
$nPl = count($players);
$avg = fn($total) => $nPl ? $total / $nPl : 0;
$tsiTotal = array_sum(array_column($players, 'tsi'));
$valueTotal = array_sum(array_map(fn($p) => PlayerFactory::value($p), $players));
$nations = [];
foreach ($players as $p) {
    $nations[$p['ccode']] = ($nations[$p['ccode']] ?? ['name' => $p['cname'], 'n' => 0]);
    $nations[$p['ccode']]['n']++;
}
uasort($nations, fn($a, $b) => $b['n'] <=> $a['n']);
$injured = count(array_filter($players, fn($p) => (float)$p['injury_weeks'] > 0));
$statSeason = GameState::season();
$cards = DB::row("SELECT COALESCE(SUM(mp.yellow), 0) AS y, COALESCE(SUM(mp.red), 0) AS r FROM match_players mp
    JOIN matches m ON m.id = mp.match_id WHERE mp.team_id = ? AND m.season = ?", [$team['id'], $statSeason]) ?: ['y' => 0, 'r' => 0];
$sl = fn(string $k, string $label) => '<a href="' . e(url('players', ['sort' => $k])) . '"' . ($sort === $k ? ' style="text-decoration:underline"' : '') . '>' . $label . '</a>';
?>
<div class="page-title">
    <h1><?= e(t('Jugadores')) ?></h1>
    <span class="muted"><?= t('{n} jugadores · Salarios: {wages}/semana', ['n' => count($players), 'wages' => '<strong>' . e(money($wages)) . '</strong>']) ?></span>
</div>

<div class="box">
    <div class="box-head"><?= e(t('Plantilla de {team}', ['team' => $team['name']])) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead>
        <tr>
            <th><?= $sl('name', e(t('Jugador'))) ?></th>
            <th><?= $sl('role', e(t('Pos'))) ?></th>
            <th><?= $sl('age', e(t('Edad'))) ?></th>
            <th class="num"><?= $sl('tsi', 'TSI') ?></th>
            <th class="num" title="<?= e(t('Forma')) ?>"><?= $sl('form', e(t('For'))) ?></th>
            <th class="num" title="<?= e(t('Condición')) ?>"><?= $sl('stamina', e(t('Cnd'))) ?></th>
            <th class="num" title="<?= e(t('Portería')) ?>"><?= $sl('keeper', e(t('Por'))) ?></th>
            <th class="num" title="<?= e(t('Defensa')) ?>"><?= $sl('defending', e(t('Def'))) ?></th>
            <th class="num" title="<?= e(t('Jugadas')) ?>"><?= $sl('playmaking', e(t('Jug'))) ?></th>
            <th class="num" title="<?= e(t('Lateral')) ?>"><?= $sl('winger', e(t('Lat'))) ?></th>
            <th class="num" title="<?= e(t('Pases')) ?>"><?= $sl('passing', e(t('Pas'))) ?></th>
            <th class="num" title="<?= e(t('Anotación')) ?>"><?= $sl('scoring', e(t('Ano'))) ?></th>
            <th class="num" title="<?= e(t('Balón parado')) ?>"><?= $sl('set_pieces', e(t('BP'))) ?></th>
            <th class="num" title="<?= e(t('Experiencia')) ?>"><?= $sl('xp', e(t('Exp'))) ?></th>
            <th><?= e(t('Esp.')) ?></th>
            <th class="num"><?= $sl('wage', e(t('Salario'))) ?></th>
            <th><?= e(t('Estado')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $p): ?>
            <tr>
                <td class="nowrap"><?= avatar_img($p, 24) ?> <?= NationService::flagImg($p['ccode'], t($p['cname'])) ?> <?= player_link($p) ?></td>
                <td><span class="tag" title="<?= e(isset(ROLES[$p['role']]) ? role_name($p['role']) : '') ?>"><?= e($p['role']) ?></span></td>
                <td class="nowrap"><?= (int)$p['age_years'] ?> <span class="muted small">(<?= (int)$p['age_days'] ?>)</span></td>
                <td class="num"><?= num($p['tsi']) ?></td>
                <td class="num" title="<?= e(form_name($p['form'])) ?>"><?= (int)floor((float)$p['form']) ?></td>
                <td class="num" title="<?= e(skill_name($p['stamina'])) ?>"><?= (int)floor((float)$p['stamina']) ?></td>
                <?php foreach (['keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring', 'set_pieces'] as $sk): ?>
                    <td class="num" title="<?= e(skill_label($p[$sk])) ?>"><?= (int)floor((float)$p[$sk]) ?></td>
                <?php endforeach; ?>
                <td class="num" title="<?= e(skill_name($p['experience'])) ?>"><?= (int)floor((float)$p['experience']) ?></td>
                <td><?= $p['specialty'] ? '<span title="' . e(specialty_name($p['specialty'])) . '">' . SPECIALTIES[$p['specialty']][1] . '</span>' : '' ?></td>
                <td class="num"><?= e(money($p['wage'])) ?></td>
                <td class="nowrap">
                    <?php if ((float)$p['injury_weeks'] > 0.5): ?><span class="tag tag-injury" title="<?= e(t('Lesionado')) ?>">+<?= ceil((float)$p['injury_weeks']) ?></span><?php elseif ((float)$p['injury_weeks'] > 0): ?><span class="tag tag-bruise" title="<?= e(t('Magullado')) ?>">🩹</span><?php endif; ?>
                    <?php if ((int)$p['suspended'] > 0): ?><span class="tag tag-red" title="<?= e(t('Sancionado')) ?>"><?= e(t('Sanc.')) ?></span><?php elseif ((int)$p['yellow_cards'] > 0): ?><span class="tag tag-card" title="<?= e(t('Tarjetas amarillas')) ?>"><?= (int)$p['yellow_cards'] ?>🟨</span><?php endif; ?>
                    <?php if ($p['listing_id']): ?><span class="tag tag-sale"><?= e(t('En venta')) ?></span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Estadísticas')) ?></div>
    <div class="table-wrap">
    <table class="table compact squad-stats">
        <thead><tr><th></th><th class="num"><?= e(t('Total')) ?></th><th class="num"><?= e(t('Promedio por jugador')) ?></th></tr></thead>
        <tbody>
            <tr><th>TSI</th><td class="num"><?= num($tsiTotal) ?></td><td class="num"><?= num($avg($tsiTotal)) ?></td></tr>
            <tr><th><?= e(t('Salario')) ?></th><td class="num"><?= e(t('{amount} / semana', ['amount' => money($wages)])) ?></td><td class="num"><?= e(t('{amount} / semana', ['amount' => money($avg($wages))])) ?></td></tr>
            <tr><th><?= e(t('Valor estimado')) ?></th><td class="num"><?= e(money($valueTotal)) ?></td><td class="num"><?= e(money($avg($valueTotal))) ?></td></tr>
            <tr><th><?= e(t('Nacionalidades')) ?></th>
                <td class="num"><?= count($nations) ?> <span class="nat-flags"><?php foreach ($nations as $code => $nt): ?><?= NationService::flagImg($code, t($nt['name']) . ' (' . $nt['n'] . ')') ?> <?php endforeach; ?></span></td>
                <td class="num"><?= e(t('{n} jugadores por nacionalidad', ['n' => num($nations ? $nPl / count($nations) : 0, 1)])) ?></td></tr>
            <tr><th><?= e(t('Lesionados')) ?></th><td class="num"><?= $injured ?></td><td class="num"><?= e(t('{n}% de la plantilla', ['n' => num($avg($injured) * 100, 1)])) ?></td></tr>
            <tr><th><?= e(t('Tarjetas rojas')) ?> <span class="muted small">(<?= e(t('Temporada {n}', ['n' => $statSeason])) ?>)</span></th><td class="num"><?= (int)$cards['r'] ?></td><td class="num"><?= num($avg((int)$cards['r']), 2) ?></td></tr>
            <tr><th><?= e(t('Tarjetas amarillas')) ?> <span class="muted small">(<?= e(t('Temporada {n}', ['n' => $statSeason])) ?>)</span></th><td class="num"><?= (int)$cards['y'] ?></td><td class="num"><?= num($avg((int)$cards['y']), 2) ?></td></tr>
        </tbody>
    </table>
    </div>
</div>
<p class="muted small"><?= e(t('Las habilidades van de 0 (inexistente) a 20 (divino). Pasa el ratón por encima de un número para ver su denominación. Pulsa en el nombre de una columna para ordenar.')) ?></p>
