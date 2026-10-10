<?php
$team = require_team();
$pageTitle = 'Jugadores';
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
$sl = fn(string $k, string $label) => '<a href="' . e(url('players', ['sort' => $k])) . '"' . ($sort === $k ? ' style="text-decoration:underline"' : '') . '>' . $label . '</a>';
?>
<div class="page-title">
    <h1>Jugadores</h1>
    <span class="muted"><?= count($players) ?> jugadores · Salarios: <strong><?= e(money($wages)) ?></strong>/semana</span>
</div>

<div class="box">
    <div class="box-head">Plantilla de <?= e($team['name']) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead>
        <tr>
            <th><?= $sl('name', 'Jugador') ?></th>
            <th><?= $sl('role', 'Pos') ?></th>
            <th><?= $sl('age', 'Edad') ?></th>
            <th class="num"><?= $sl('tsi', 'TSI') ?></th>
            <th class="num" title="Forma"><?= $sl('form', 'For') ?></th>
            <th class="num" title="Condición"><?= $sl('stamina', 'Cnd') ?></th>
            <th class="num" title="Portería"><?= $sl('keeper', 'Por') ?></th>
            <th class="num" title="Defensa"><?= $sl('defending', 'Def') ?></th>
            <th class="num" title="Jugadas"><?= $sl('playmaking', 'Jug') ?></th>
            <th class="num" title="Lateral"><?= $sl('winger', 'Lat') ?></th>
            <th class="num" title="Pases"><?= $sl('passing', 'Pas') ?></th>
            <th class="num" title="Anotación"><?= $sl('scoring', 'Ano') ?></th>
            <th class="num" title="Balón parado"><?= $sl('set_pieces', 'BP') ?></th>
            <th class="num" title="Experiencia"><?= $sl('xp', 'Exp') ?></th>
            <th>Esp.</th>
            <th class="num"><?= $sl('wage', 'Salario') ?></th>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $p): ?>
            <tr>
                <td class="nowrap"><?= avatar_img($p, 24) ?> <span title="<?= e($p['cname']) ?>"><?= NationService::flag($p['ccode']) ?></span> <?= player_link($p) ?></td>
                <td><span class="tag" title="<?= e(ROLES[$p['role']] ?? '') ?>"><?= e($p['role']) ?></span></td>
                <td class="nowrap"><?= (int)$p['age_years'] ?> <span class="muted small">(<?= (int)$p['age_days'] ?>)</span></td>
                <td class="num"><?= num($p['tsi']) ?></td>
                <td class="num" title="<?= e(form_name($p['form'])) ?>"><?= (int)floor((float)$p['form']) ?></td>
                <td class="num" title="<?= e(skill_name($p['stamina'])) ?>"><?= (int)floor((float)$p['stamina']) ?></td>
                <?php foreach (['keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring', 'set_pieces'] as $sk): ?>
                    <td class="num" title="<?= e(skill_label($p[$sk])) ?>"><?= (int)floor((float)$p[$sk]) ?></td>
                <?php endforeach; ?>
                <td class="num" title="<?= e(skill_name($p['experience'])) ?>"><?= (int)floor((float)$p['experience']) ?></td>
                <td><?= $p['specialty'] ? '<span title="' . e(SPECIALTIES[$p['specialty']][0]) . '">' . SPECIALTIES[$p['specialty']][1] . '</span>' : '' ?></td>
                <td class="num"><?= e(money($p['wage'])) ?></td>
                <td class="nowrap">
                    <?php if ((float)$p['injury_weeks'] > 0.5): ?><span class="tag tag-injury" title="Lesionado">+<?= ceil((float)$p['injury_weeks']) ?></span><?php elseif ((float)$p['injury_weeks'] > 0): ?><span class="tag tag-bruise" title="Magullado">🩹</span><?php endif; ?>
                    <?php if ((int)$p['suspended'] > 0): ?><span class="tag tag-red" title="Sancionado">Sanc.</span><?php elseif ((int)$p['yellow_cards'] > 0): ?><span class="tag tag-card" title="Tarjetas amarillas"><?= (int)$p['yellow_cards'] ?>🟨</span><?php endif; ?>
                    <?php if ($p['listing_id']): ?><span class="tag tag-sale">En venta</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<p class="muted small">Las habilidades van de 0 (inexistente) a 20 (divino). Pasa el ratón por encima de un número para ver su denominación. Pulsa en el nombre de una columna para ordenar.</p>
