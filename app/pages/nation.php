<?php
require_login();
$my = current_team();
$id = (int)get('id', $my['country_id'] ?? 1);
$c = DB::row('SELECT * FROM countries WHERE id = ?', [$id]);
if (!$c) {
    flash('error', t('País no encontrado.'));
    redirect(url('nations'));
}
$pageTitle = t($c['name']);
$navActive = 'nations';
$national = NationService::nationalTeam($id);
$squad = NationService::squad($id, true);
$injured = DB::all('SELECT p.*, t.name AS club_name FROM players p JOIN teams t ON t.id = p.team_id
    WHERE p.country_id = ? AND p.retired = 0 AND p.injury_weeks > 0 AND t.is_national = 0 ORDER BY p.tsi DESC LIMIT 5', [$id]);
$units = DB::all('SELECT lu.*, (SELECT COUNT(*) FROM teams t WHERE t.league_unit_id = lu.id AND t.user_id IS NOT NULL) AS humans
    FROM league_units lu WHERE lu.country_id = ? ORDER BY level, number', [$id]);
$byLevel = [];
foreach ($units as $u) {
    $byLevel[(int)$u['level']][] = $u;
}
$stats = DB::row('SELECT COUNT(*) teams, SUM(user_id IS NOT NULL) managers FROM teams WHERE country_id = ? AND is_national = 0', [$id]);
$players = (int)DB::value('SELECT COUNT(*) FROM players WHERE country_id = ? AND retired = 0 AND team_id IS NOT NULL', [$id]);
$abroad = (int)DB::value('SELECT COUNT(*) FROM players p JOIN teams t ON t.id = p.team_id WHERE p.country_id = ? AND p.retired = 0 AND t.country_id <> ? AND t.is_national = 0', [$id, $id]);
$matches = $national ? LeagueService::teamMatches((int)$national['id'], '1=1', [], 'x.scheduled_at DESC', 10) : [];
$history = $national ? DB::all('SELECT w.season, w.status, w.champion_team_id, w.runnerup_team_id, w.third_team_id, wt.group_letter
    FROM worldcup_teams wt JOIN worldcups w ON w.id = wt.cup_id WHERE wt.team_id = ? ORDER BY w.season DESC', [$national['id']]) : [];
$topScorers = DB::all('SELECT p.*, t.name AS club_name FROM players p LEFT JOIN teams t ON t.id = p.team_id
    WHERE p.country_id = ? AND p.goals_national > 0 ORDER BY p.goals_national DESC, p.caps DESC LIMIT 5', [$id]);
$roleOrder = array_flip(array_keys(NationService::QUOTA));
?>
<div class="page-title">
    <h1><?= flag($c['code']) ?> <?= e(t($c['name'])) ?></h1>
    <a href="<?= e(url('nations')) ?>">« <?= e(t('Todas las naciones')) ?></a>
</div>

<div class="stat-tiles">
    <div class="tile"><div class="tv"><?= num($stats['teams']) ?></div><div class="tl"><?= e(t('Equipos en su liga')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($stats['managers']) ?></div><div class="tl"><?= e(t('Mánagers')) ?></div></div>
    <div class="tile"><div class="tv"><?= num($players) ?></div><div class="tl"><?= e(t('Jugadores {country}', ['country' => t($c['name'])])) ?></div></div>
    <div class="tile"><div class="tv"><?= num($abroad) ?></div><div class="tl"><?= e(t('Juegan en el extranjero')) ?></div></div>
</div>

<div class="box">
    <div class="box-head"><?= e(t('Liga de {country}', ['country' => t($c['name'])])) ?></div>
    <div class="box-body">
        <?php foreach ($byLevel as $lvl => $list): ?>
            <p><strong><?= e(t('División {n}', ['n' => LeagueService::roman($lvl)])) ?></strong> (<?= e(count($list) > 1 ? t('{n} grupos', ['n' => count($list)]) : t('{n} grupo', ['n' => count($list)])) ?>):
            <?php foreach ($list as $u): ?><a href="<?= e(url('league', ['unit' => $u['id']])) ?>"><?= e($u['name']) ?></a><?= (int)$u['humans'] ? '<sup class="small" title="' . e(t('mánagers')) . '">' . (int)$u['humans'] . '</sup>' : '' ?>&nbsp; <?php endforeach; ?></p>
        <?php endforeach; ?>
        <?php if (!$byLevel): ?><p class="muted"><?= e(t('Este país todavía no tiene liga.')) ?></p><?php endif; ?>
    </div>
</div>

<div class="box">
    <div class="box-head"><?= e(t('Selección de {country}', ['country' => t($c['name'])])) ?> · <?= e(t('Convocatoria actual')) ?>
        <span class="small"><?= e(t('Fuerza: {n} TSI', ['n' => num(array_sum(array_column($squad, 'tsi')))])) ?></span></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th><?= e(t('Pos')) ?></th><th><?= e(t('Jugador')) ?></th><th><?= e(t('Club')) ?></th><th><?= e(t('Edad')) ?></th><th class="num">TSI</th><th class="num"><?= e(t('For')) ?></th><th class="num"><?= e(t('Por')) ?></th><th class="num"><?= e(t('Def')) ?></th><th class="num" title="<?= e(t('Jugadas')) ?>"><?= e(t('Jug')) ?></th><th class="num" title="<?= e(t('Lateral')) ?>"><?= e(t('Lat')) ?></th><th class="num"><?= e(t('Pas')) ?></th><th class="num"><?= e(t('Ano')) ?></th><th><?= e(t('Esp.')) ?></th><th class="num"><?= e(t('Int.')) ?></th><th class="num"><?= e(t('Goles')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($squad as $p): $mine = $my && (int)$p['team_id'] === (int)$my['id']; ?>
            <tr<?= $mine ? ' class="me"' : '' ?>>
                <td><span class="tag" title="<?= e(isset(ROLES[$p['squad_role']]) ? role_name($p['squad_role']) : '') ?>"><?= e($p['squad_role']) ?></span></td>
                <td class="nowrap"><?= avatar_img($p, 22) ?> <?= player_link($p) ?></td>
                <td class="small"><?= team_link($p['team_id'], $p['club_name']) ?></td>
                <td><?= (int)$p['age_years'] ?></td>
                <td class="num"><strong><?= num($p['tsi']) ?></strong></td>
                <td class="num"><?= (int)floor((float)$p['form']) ?></td>
                <?php foreach (['keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring'] as $sk): ?>
                    <td class="num" title="<?= e(skill_label($p[$sk])) ?>"><?= (int)floor((float)$p[$sk]) ?></td>
                <?php endforeach; ?>
                <td><?= $p['specialty'] ? SPECIALTIES[$p['specialty']][1] : '' ?></td>
                <td class="num"><?= (int)$p['caps'] ?></td>
                <td class="num"><?= (int)$p['goals_national'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$squad): ?><tr><td colspan="15" class="muted"><?= e(t('No hay jugadores disponibles de esta nacionalidad.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="box-body small muted">
        <?= e(t('Se convoca automáticamente a los jugadores disponibles con mayor TSI de cada posición: 2 porteros, 3 centrales, 3 laterales, 4 mediocampistas, 3 extremos y 3 delanteros. La convocatoria se recalcula antes de cada partido. Int. = partidos internacionales.')) ?>
        <?php if ($injured): ?><br><?= e(t('Bajas por lesión:')) ?> <?php foreach ($injured as $i => $p): ?><?= $i ? ', ' : '' ?><?= player_link($p) ?> (TSI <?= num($p['tsi']) ?>)<?php endforeach; ?>.<?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Partidos de la selección')) ?></div>
        <table class="table compact">
            <?php foreach ($matches as $m): ?>
                <tr>
                    <td class="small nowrap"><?= e(fdate($m['scheduled_at'], false)) ?></td>
                    <td class="small"><?= e(WorldCupService::stageName($m['cup_stage'])) ?></td>
                    <td><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><?= e($m['hn']) ?> <strong><?= $m['status'] === 'played' ? e(score_label($m)) : 'vs' ?></strong> <?= e($m['an']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$matches): ?><tr><td class="muted"><?= e(t('Sin partidos todavía.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="stack">
        <div class="box">
            <div class="box-head"><?= e(t('Historial en el Mundial')) ?></div>
            <table class="table compact">
                <?php foreach ($history as $h):
                    $nid = (int)$national['id'];
                    $res = $h['status'] !== 'finished' ? t('En juego') : ((int)$h['champion_team_id'] === $nid ? '🏆 ' . t('Campeón') : ((int)$h['runnerup_team_id'] === $nid ? '🥈 ' . t('Subcampeón') : ((int)$h['third_team_id'] === $nid ? '🥉 ' . t('Tercero') : t('Participante'))));
                ?>
                    <tr><td><?= e(t('Temporada {n}', ['n' => (int)$h['season']])) ?></td><td><?= e(t('Grupo {letter}', ['letter' => WorldCupService::letterLabel($h['group_letter'])])) ?></td><td><a href="<?= e(url('worldcup', ['season' => $h['season']])) ?>"><?= e($res) ?></a></td></tr>
                <?php endforeach; ?>
                <?php if (!$history): ?><tr><td class="muted"><?= e(t('Aún no ha disputado ningún Mundial.')) ?></td></tr><?php endif; ?>
            </table>
        </div>
        <div class="box">
            <div class="box-head"><?= e(t('Máximos goleadores de la selección')) ?></div>
            <table class="table compact">
                <?php foreach ($topScorers as $p): ?>
                    <tr><td><?= player_link($p) ?></td><td class="small muted"><?= e($p['club_name'] ?? t('retirado')) ?></td><td class="num"><?= e(t('{n} goles', ['n' => (int)$p['goals_national']])) ?></td><td class="num small"><?= e(t('{n} int.', ['n' => (int)$p['caps']])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$topScorers): ?><tr><td class="muted"><?= e(t('Todavía no hay goles.')) ?></td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
