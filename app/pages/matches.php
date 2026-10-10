<?php
require_login();
$my = current_team();
$teamId = (int)(get('team') ?: ($my['id'] ?? 0));
$team = DB::row('SELECT * FROM teams WHERE id = ?', [$teamId]);
if (!$team) {
    redirect(url());
}
$season = (int)(get('season') ?: GameState::season());
$pageTitle = t('Partidos');
$navActive = $my && (int)$my['id'] === $teamId ? 'club' : 'world';
$matches = LeagueService::teamMatches($teamId, 'season = ?', [$season]);
$isMine = $my && (int)$my['id'] === $teamId;
$crestOf = fn(array $m, string $x) => ['id' => $m[$x === 'h' ? 'home_team_id' : 'away_team_id'], 'crest_id' => $m[$x . '_crest_id'], 'crest_custom' => $m[$x . '_crest_custom']];
$seasons = array_column(DB::all('SELECT DISTINCT season FROM ((SELECT season FROM matches WHERE home_team_id = ?) UNION (SELECT season FROM matches WHERE away_team_id = ?)) x ORDER BY season DESC', [$teamId, $teamId]), 'season');
?>
<div class="page-title">
    <h1><?= e(t('Partidos de {team}', ['team' => $team['name']])) ?></h1>
    <form class="inline-form" method="get">
        <input type="hidden" name="page" value="matches"><input type="hidden" name="team" value="<?= $teamId ?>">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons ?: [$season] as $s): ?><option value="<?= (int)$s ?>"<?= (int)$s === $season ? ' selected' : '' ?>><?= e(t('Temporada {n}', ['n' => (int)$s])) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Temporada {n}', ['n' => $season])) ?></div>
    <div class="table-wrap">
    <table class="table matches-table">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Tipo')) ?></th><th class="right"><?= e(t('Local')) ?></th><th class="center"><?= e(t('Resultado')) ?></th><th><?= e(t('Visitante')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($matches as $m):
            $played = $m['status'] === 'played';
            $res = '';
            if ($played) {
                $isHome = (int)$m['home_team_id'] === $teamId;
                $gf = $isHome ? (int)$m['home_goals'] : (int)$m['away_goals'];
                $gc = $isHome ? (int)$m['away_goals'] : (int)$m['home_goals'];
                $res = $gf > $gc ? 'V' : ($gf === $gc ? 'E' : 'D');
                $resLabel = ['V' => t('V##victoria'), 'E' => t('E##empate'), 'D' => t('D##derrota')][$res];
            }
        ?>
            <tr>
                <td class="nowrap small"><?= e(date('d/m/y H:i', strtotime($m['scheduled_at']))) ?></td>
                <td class="small"><?= $m['type'] === 'league' ? e(t('Liga J{n}', ['n' => (int)$m['round']])) : e(match_type_label($m)) ?></td>
                <td class="right nowrap"><span class="mt-name" title="<?= e($m['hn']) ?>"><?= (int)$m['home_team_id'] === $teamId ? '<strong>' . e($m['hn']) . '</strong>' : team_link($m['home_team_id'], $m['hn']) ?></span> <?= crest_img($crestOf($m, 'h'), 22) ?></td>
                <td class="center nowrap"><?= $played ? '<strong>' . e(score_label($m)) . '</strong>' : '<span class="muted">- : -</span>' ?></td>
                <td class="nowrap"><?= crest_img($crestOf($m, 'a'), 22) ?> <span class="mt-name" title="<?= e($m['an']) ?>"><?= (int)$m['away_team_id'] === $teamId ? '<strong>' . e($m['an']) . '</strong>' : team_link($m['away_team_id'], $m['an']) ?></span></td>
                <td class="nowrap match-actions"><?php if ($played): ?><span class="form-badge form-<?= $res ?>"><?= e($resLabel) ?></span> <a class="btn btn-small btn-grey" href="<?= e(url('match', ['id' => $m['id']])) ?>">📰 <?= e(t('Crónica')) ?></a><?php else: ?><a class="btn btn-small btn-grey" href="<?= e(url('match', ['id' => $m['id']])) ?>">🔎 <?= e(t('Previa')) ?></a><?php if ($isMine): $sentM = Lineup::sentFor($teamId, (int)$m['id']) !== null; ?> <a class="btn btn-small<?= $sentM ? '' : ' btn-accent' ?>" href="<?= e(url('lineup', ['match' => $m['id']])) ?>" title="<?= e($sentM ? t('Alineación enviada') : t('Sin enviar: se usará la predeterminada')) ?>"><?= $sentM ? '✅' : '📋' ?> <?= e(t('Alineación')) ?></a><?php endif; ?><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$matches): ?><tr><td colspan="6" class="muted"><?= e(t('No hay partidos.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
