<?php
require_login();
$pageTitle = t('Mundial');
$navActive = 'worldcup';
$seasons = array_map('intval', array_column(DB::all('SELECT season FROM worldcups ORDER BY season DESC'), 'season'));
$season = (int)(get('season') ?: ($seasons[0] ?? GameState::season()));
$cup = WorldCupService::forSeason($season);
$titles = WorldCupService::titles();
?>
<div class="page-title">
    <h1>🏅 <?= e(t('Mundial de Fútbol')) ?></h1>
    <?php if ($seasons): ?>
    <form class="inline-form" method="get">
        <input type="hidden" name="page" value="worldcup">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?><option value="<?= $s ?>"<?= $s === $season ? ' selected' : '' ?>><?= e(t('Mundial de la temporada {n}', ['n' => $s])) ?></option><?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if (!$cup): ?>
    <div class="box"><div class="box-body">
        <?php if (!WorldCupService::enabled()): ?>
            <p><?= t('El Mundial está desactivado en <code>config.ini</code> (sección <code>[worldcup]</code>).') ?></p>
        <?php else: ?>
            <p><?= e(t('No hay Mundial programado para la temporada {season}. Se juega cada {n} temporada(s), a partir de la semana {week}.', ['season' => $season, 'n' => max(1, Config::int('worldcup', 'every_seasons', 1)), 'week' => Config::int('worldcup', 'start_week', 3)])) ?></p>
        <?php endif; ?>
        <p><?= t('Participan las selecciones de las {link}: cada una convoca a sus jugadores con mayor TSI en cada posición.', ['link' => '<a href="' . e(url('nations')) . '">' . e(t('naciones')) . '</a>']) ?></p>
    </div></div>
<?php return; endif;

$cupId = (int)$cup['id'];
$letters = array_column(DB::all('SELECT DISTINCT group_letter FROM worldcup_teams WHERE cup_id = ? ORDER BY group_letter', [$cupId]), 'group_letter');
$all = DB::all('SELECT m.*, h.name AS hn, a.name AS an, hc.code AS hcode, ac.code AS acode, h.country_id AS hcid, a.country_id AS acid
    FROM matches m JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    JOIN countries hc ON hc.id = h.country_id JOIN countries ac ON ac.id = a.country_id
    WHERE m.type = \'worldcup\' AND m.cup_id = ? ORDER BY m.round, m.id', [$cupId]);
$byStage = [];
foreach ($all as $m) {
    $byStage[$m['cup_stage']][] = $m;
}
$next = DB::row("SELECT * FROM scheduled_events WHERE type = 'worldcup_round' AND season = ? AND status = 'pending' ORDER BY run_at LIMIT 1", [$season]);
$scorers = DB::all('SELECT p.id, p.first_name, p.last_name, c.code, c.name AS cname, SUM(mp.goals) g FROM match_players mp
    JOIN matches m ON m.id = mp.match_id JOIN players p ON p.id = mp.player_id JOIN countries c ON c.id = p.country_id
    WHERE m.type = \'worldcup\' AND m.cup_id = ? GROUP BY p.id, p.first_name, p.last_name, c.code, c.name HAVING g > 0 ORDER BY g DESC LIMIT 10', [$cupId]);
$teamName = fn($id) => $id ? DB::row('SELECT t.name, t.country_id, c.code FROM teams t JOIN countries c ON c.id = t.country_id WHERE t.id = ?', [$id]) : null;
$gameRow = function (array $m) {
    $played = $m['status'] === 'played';
    return '<tr><td class="right">' . nation_link($m['hcid'], $m['hn'], $m['hcode']) . '</td>'
        . '<td class="center nowrap"><a href="' . e(url('match', ['id' => $m['id']])) . '">' . ($played ? '<strong>' . e(score_label($m)) . '</strong>' : e(date('d/m H:i', strtotime($m['scheduled_at'])))) . '</a></td>'
        . '<td>' . nation_link($m['acid'], $m['an'], $m['acode']) . '</td></tr>';
};
?>

<?php if ($cup['status'] === 'finished'):
    $ch = $teamName($cup['champion_team_id']);
    $ru = $teamName($cup['runnerup_team_id']);
    $th = $teamName($cup['third_team_id']);
?>
    <div class="scoreboard" style="grid-template-columns:1fr">
        <div class="team center" style="font-size:22px">🏆 <?= flag($ch['code']) ?> <?= t('{team}, campeón del Mundial', ['team' => nation_link($ch['country_id'], $ch['name'])]) ?></div>
        <div class="meta">🥈 <?= e(isset($ru['name']) ? t($ru['name']) : '-') ?> · 🥉 <?= e(isset($th['name']) ? t($th['name']) : '-') ?> · <?= e(t('Temporada {n}', ['n' => $season])) ?></div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <?= e($cup['status'] === 'groups' ? t('Fase de grupos en juego.') : t('Fase eliminatoria en juego.')) ?>
        <?php if ($next): ?> <?= e(t('Próxima jornada:')) ?> <strong><?= e(fdate_long($next['run_at'])) ?></strong> (<span data-deadline="<?= e(date('c', strtotime($next['run_at']))) ?>"></span>).<?php endif; ?>
        <?= e(t('Los partidos se juegan en campo neutral; en las eliminatorias, si hay empate, se decide por penaltis.')) ?>
    </div>
<?php endif; ?>

<h2><?= e(t('Fase de grupos')) ?></h2>
<div class="grid-2">
<?php foreach ($letters as $l): $table = WorldCupService::groupTable($cupId, $l); ?>
    <div class="box">
        <div class="box-head"><?= e(t('Grupo {letter}', ['letter' => WorldCupService::letterLabel($l)])) ?></div>
        <table class="table compact">
            <thead><tr><th>#</th><th><?= e(t('Selección')) ?></th><th class="num"><?= e(t('PJ')) ?></th><th class="num"><?= e(t('G##ganados')) ?></th><th class="num"><?= e(t('E##empatados')) ?></th><th class="num"><?= e(t('P##perdidos')) ?></th><th class="num"><?= e(t('Goles')) ?></th><th class="num"><?= e(t('Pts')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($table as $r): ?>
                <tr class="<?= $r['pos'] <= 2 ? 'promo' : '' ?>">
                    <td><?= $r['pos'] ?></td>
                    <td><?= nation_link($r['country_id'], $r['name'], $r['code']) ?><?= isset($titles[$r['team_id']]) ? ' <span class="small" title="' . e(t('Mundiales ganados')) . '">' . str_repeat('★', $titles[$r['team_id']]) . '</span>' : '' ?></td>
                    <td class="num"><?= $r['pj'] ?></td><td class="num"><?= $r['g'] ?></td><td class="num"><?= $r['e'] ?></td><td class="num"><?= $r['p'] ?></td>
                    <td class="num"><?= $r['gf'] ?>:<?= $r['gc'] ?></td><td class="num"><strong><?= $r['pts'] ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="table compact small">
            <?php foreach ($byStage[$l] ?? [] as $m) { echo $gameRow($m); } ?>
        </table>
    </div>
<?php endforeach; ?>
</div>
<br>
<h2><?= e(t('Eliminatorias')) ?></h2>
<div class="ko-grid">
    <?php foreach (['R16', 'QF', 'SF', 'F'] as $st): if (($st === 'QF' && count($letters) < 4) || ($st === 'R16' && count($letters) < 8)) { continue; } ?>
        <div class="box">
            <div class="box-head"><?= e(WorldCupService::stageName($st)) ?></div>
            <table class="table compact">
                <?php foreach ($byStage[$st] ?? [] as $m) { echo $gameRow($m); } ?>
                <?php if (empty($byStage[$st])): ?><tr><td class="muted"><?= e(t('Por decidir')) ?></td></tr><?php endif; ?>
            </table>
            <?php if ($st === 'F'): ?>
                <div class="box-head"><?= e(WorldCupService::stageName('3P')) ?></div>
                <table class="table compact">
                    <?php foreach ($byStage['3P'] ?? [] as $m) { echo $gameRow($m); } ?>
                    <?php if (empty($byStage['3P'])): ?><tr><td class="muted"><?= e(t('Por decidir')) ?></td></tr><?php endif; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<br>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Máximos goleadores del torneo')) ?></div>
        <table class="table compact">
            <?php foreach ($scorers as $i => $s): ?>
                <tr><td><?= $i + 1 ?></td><td><?= player_link($s) ?></td><td class="small"><?= flag($s['code']) ?> <?= e(t($s['cname'])) ?></td><td class="num"><strong><?= (int)$s['g'] ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (!$scorers): ?><tr><td class="muted"><?= e(t('Todavía no hay goles.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Palmarés')) ?></div>
        <table class="table compact">
            <?php foreach (DB::all("SELECT w.season, t.name, t.country_id, c.code FROM worldcups w JOIN teams t ON t.id = w.champion_team_id JOIN countries c ON c.id = t.country_id WHERE w.status = 'finished' ORDER BY w.season DESC") as $w): ?>
                <tr><td><?= e(t('Temporada {n}', ['n' => (int)$w['season']])) ?></td><td>🏆 <?= nation_link($w['country_id'], $w['name'], $w['code']) ?></td></tr>
            <?php endforeach; ?>
            <tr><td colspan="2" class="small muted"><?= e(t('Formato: 32 selecciones en 8 grupos de 4 (pasan las dos primeras), octavos, cuartos, semifinales, tercer puesto y final. Una jornada por semana ({day}, {time}).', ['day' => weekday_name(Config::int('worldcup', 'weekday', 2)), 'time' => (string)Config::get('worldcup', 'time', '20:00')])) ?></td></tr>
        </table>
    </div>
</div>
