<?php
require_login();
$my = current_team();
$teamId = (int)(get('team') ?: ($my['id'] ?? 0));
$team = DB::row('SELECT * FROM teams WHERE id = ?', [$teamId]);
if (!$team) {
    redirect(url());
}
$season = (int)(get('season') ?: GameState::season());
$pageTitle = 'Partidos';
$navActive = $my && (int)$my['id'] === $teamId ? 'club' : 'world';
$matches = LeagueService::teamMatches($teamId, 'season = ?', [$season]);
$seasons = array_column(DB::all('SELECT DISTINCT season FROM ((SELECT season FROM matches WHERE home_team_id = ?) UNION (SELECT season FROM matches WHERE away_team_id = ?)) x ORDER BY season DESC', [$teamId, $teamId]), 'season');
?>
<div class="page-title">
    <h1>Partidos de <?= e($team['name']) ?></h1>
    <form class="inline-form" method="get">
        <input type="hidden" name="page" value="matches"><input type="hidden" name="team" value="<?= $teamId ?>">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons ?: [$season] as $s): ?><option value="<?= (int)$s ?>"<?= (int)$s === $season ? ' selected' : '' ?>>Temporada <?= (int)$s ?></option><?php endforeach; ?>
        </select>
    </form>
</div>
<div class="box">
    <div class="box-head">Temporada <?= $season ?></div>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Tipo</th><th class="right">Local</th><th class="center">Resultado</th><th>Visitante</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($matches as $m):
            $played = $m['status'] === 'played';
            $res = '';
            if ($played) {
                $isHome = (int)$m['home_team_id'] === $teamId;
                $gf = $isHome ? (int)$m['home_goals'] : (int)$m['away_goals'];
                $gc = $isHome ? (int)$m['away_goals'] : (int)$m['home_goals'];
                $res = $gf > $gc ? 'V' : ($gf === $gc ? 'E' : 'D');
            }
        ?>
            <tr>
                <td class="nowrap"><?= e(fdate($m['scheduled_at'])) ?></td>
                <td><?= $m['type'] === 'league' ? 'Liga J' . (int)$m['round'] : e(match_type_label($m)) ?></td>
                <td class="right"><?= (int)$m['home_team_id'] === $teamId ? '<strong>' . e($m['hn']) . '</strong>' : team_link($m['home_team_id'], $m['hn']) ?></td>
                <td class="center nowrap"><?= $played ? '<strong>' . e(score_label($m)) . '</strong>' : '<span class="muted">- : -</span>' ?></td>
                <td><?= (int)$m['away_team_id'] === $teamId ? '<strong>' . e($m['an']) . '</strong>' : team_link($m['away_team_id'], $m['an']) ?></td>
                <td class="nowrap"><?php if ($played): ?><span class="form-badge form-<?= $res ?>"><?= $res ?></span> <a href="<?= e(url('match', ['id' => $m['id']])) ?>">Crónica</a><?php else: ?><a href="<?= e(url('match', ['id' => $m['id']])) ?>">Previa</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$matches): ?><tr><td colspan="6" class="muted">No hay partidos.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
