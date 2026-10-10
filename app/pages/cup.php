<?php
$user = require_login();
$myTeam = current_team();
$pageTitle = t('Copa');
$navActive = 'league';
$countryId = (int)(get('country') ?: ($myTeam['country_id'] ?? 1));
$countryRow = DB::row('SELECT * FROM countries WHERE id = ?', [$countryId]) ?: DB::row('SELECT * FROM countries ORDER BY id LIMIT 1');
$countryId = (int)$countryRow['id'];
$seasons = array_map('intval', array_column(DB::all('SELECT season FROM league_cups WHERE country_id = ? ORDER BY season DESC', [$countryId]), 'season'));
$season = (int)(get('season') ?: ($seasons[0] ?? GameState::season()));
$cup = DB::row('SELECT * FROM league_cups WHERE country_id = ? AND season = ?', [$countryId, $season]);
$countries = DB::all('SELECT c.id, c.name, c.code FROM countries c WHERE EXISTS (SELECT 1 FROM league_cups lc WHERE lc.country_id = c.id) ORDER BY c.name');
$cupName = t('Copa de {country}', ['country' => t($countryRow['name'])]);
?>
<div class="page-title">
    <h1>🥇 <?= flag($countryRow['code']) ?> <?= e($cupName) ?></h1>
    <form class="inline-form" method="get">
        <input type="hidden" name="page" value="cup">
        <select name="country" onchange="this.form.submit()">
            <?php foreach ($countries as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $countryId ? ' selected' : '' ?>><?= e(t($c['name'])) ?></option><?php endforeach; ?>
        </select>
        <?php if ($seasons): ?>
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?><option value="<?= $s ?>"<?= $s === $season ? ' selected' : '' ?>><?= e(t('Temporada {n}', ['n' => $s])) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
    </form>
</div>

<?php if (!$cup): ?>
    <div class="box"><div class="box-body">
        <?php if (!CupService::enabled()): ?>
            <p><?= t('La Copa está desactivada en <code>config.ini</code> (sección <code>[cup]</code>).') ?></p>
        <?php else: ?>
            <p><?= e(t('No hay Copa de {country} en la temporada {season}. La Copa se sortea al comenzar cada temporada.', ['country' => t($countryRow['name']), 'season' => $season])) ?></p>
        <?php endif; ?>
        <p class="small muted"><?= e(t('En la Copa participan todos los equipos de todas las divisiones del país, a eliminación directa. Los partidos se juegan en la fecha de los amistosos.')) ?></p>
    </div></div>
<?php return; endif;

$cupId = (int)$cup['id'];
$total = (int)DB::value('SELECT COUNT(*) FROM cup_entries WHERE cup_id = ?', [$cupId]);
$alive = (int)DB::value('SELECT COUNT(*) FROM cup_entries WHERE cup_id = ? AND eliminated_round IS NULL', [$cupId]);
$rounds = DB::all("SELECT round, cup_stage, MIN(scheduled_at) AS at, COUNT(*) AS n, SUM(status = 'played') AS played
    FROM matches WHERE type = 'cup' AND cup_id = ? GROUP BY round, cup_stage ORDER BY round DESC", [$cupId]);
$viewRound = (int)(get('round') ?: ($rounds[0]['round'] ?? 1));
$games = DB::all("SELECT m.*, h.name AS hn, a.name AS an, hl.level AS hlvl, al.level AS alvl FROM matches m
    JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    LEFT JOIN league_units hl ON hl.id = h.league_unit_id LEFT JOIN league_units al ON al.id = a.league_unit_id
    WHERE m.type = 'cup' AND m.cup_id = ? AND m.round = ? ORDER BY (m.home_team_id = ? OR m.away_team_id = ?) DESC, LEAST(COALESCE(hl.level,9), COALESCE(al.level,9)), m.id",
    [$cupId, $viewRound, $myTeam['id'] ?? 0, $myTeam['id'] ?? 0]);
$mine = $myTeam ? CupService::teamCup((int)$myTeam['id'], $season) : null;
$mine = ($mine && (int)$mine['id'] === $cupId) ? $mine : null;
$myMatch = $mine ? DB::row("SELECT m.*, h.name AS hn, a.name AS an FROM matches m JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    WHERE m.type = 'cup' AND m.cup_id = ? AND (m.home_team_id = ? OR m.away_team_id = ?) ORDER BY m.round DESC LIMIT 1", [$cupId, $myTeam['id'], $myTeam['id']]) : null;
$stageOfRound = [];
foreach ($rounds as $r) {
    $stageOfRound[(int)$r['round']] = $r['cup_stage'];
}
$teamRow = fn($id) => $id ? DB::row('SELECT id, name FROM teams WHERE id = ?', [$id]) : null;
$scorers = DB::all("SELECT p.id, p.first_name, p.last_name, t.id AS tid, t.name AS tn, SUM(mp.goals) g FROM match_players mp
    JOIN matches m ON m.id = mp.match_id JOIN players p ON p.id = mp.player_id JOIN teams t ON t.id = mp.team_id
    WHERE m.type = 'cup' AND m.cup_id = ? GROUP BY p.id, p.first_name, p.last_name, t.id, t.name HAVING g > 0 ORDER BY g DESC LIMIT 10", [$cupId]);
$history = DB::all('SELECT lc.season, w.id AS wid, w.name AS wn, r.id AS rid, r.name AS rn FROM league_cups lc
    LEFT JOIN teams w ON w.id = lc.winner_team_id LEFT JOIN teams r ON r.id = lc.runnerup_team_id
    WHERE lc.country_id = ? AND lc.winner_team_id IS NOT NULL ORDER BY lc.season DESC LIMIT 10', [$countryId]);
?>

<?php if ($cup['status'] === 'finished' && $cup['winner_team_id']): $w = $teamRow($cup['winner_team_id']); $r = $teamRow($cup['runnerup_team_id']); ?>
<div class="box">
    <div class="box-body sponsor-main">
        <img src="<?= e(asset('img/trophies/trophy-cup.svg')) ?>" width="110" alt="<?= e(t('Copa')) ?>">
        <div>
            <h2><?= e(t('Campeón:')) ?> <?= team_link($w['id'], $w['name']) ?></h2>
            <?php if ($r): ?><div class="muted"><?= e(t('Subcampeón:')) ?> <?= team_link($r['id'], $r['name']) ?></div><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('La competición')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Estado')) ?></th><td><?= $cup['status'] === 'finished' ? e(t('Terminada')) : e(t('En juego')) . ' · ' . e(CupService::stageName($stageOfRound[(int)$cup['current_round']] ?? null)) ?></td></tr>
            <tr><th><?= e(t('Participantes')) ?></th><td><?= e(t('{n} equipos', ['n' => $total])) ?><?= $cup['status'] === 'playing' ? ' ' . e(t('({n} siguen vivos)', ['n' => $alive])) : '' ?></td></tr>
            <tr><th><?= e(t('Premios')) ?></th><td><?= e(t('Pasar ronda:')) ?> <?= e(money(Config::int('cup', 'round_prize', 8000))) ?> · <?= e(t('Campeón:')) ?> <?= e(money(Config::int('cup', 'winner_prize', 150000))) ?> · <?= e(t('Subcampeón:')) ?> <?= e(money(Config::int('cup', 'runnerup_prize', 60000))) ?></td></tr>
        </table>
        <div class="box-body small muted"><?= e(t('Eliminación directa a un partido: juega en casa el equipo de la división inferior y, si hay empate, se decide en la tanda de penaltis. Mientras sigas vivo en la Copa no podrás jugar amistosos.')) ?></div>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Mi equipo')) ?></div><div class="box-body">
        <?php if (!$myTeam || !$mine): ?>
            <p class="muted"><?= e(t('Tu equipo no participa en esta Copa.')) ?></p>
        <?php elseif ($mine['eliminated_round'] === null && $cup['status'] === 'playing'): ?>
            <p>✅ <?= t('<strong>Sigues vivo</strong> en la Copa.') ?></p>
            <?php if ($myMatch && $myMatch['status'] === 'scheduled'): ?>
                <p><?= e(t('Próximo partido ({stage}):', ['stage' => CupService::stageName($myMatch['cup_stage'])])) ?> <a href="<?= e(url('match', ['id' => $myMatch['id']])) ?>"><?= e($myMatch['hn']) ?> – <?= e($myMatch['an']) ?></a><br><span class="small muted"><?= e(fdate($myMatch['scheduled_at'])) ?></span></p>
            <?php else: ?>
                <p class="small muted"><?= e(t('Esta ronda pasas directamente (exento) o el rival se sortea tras la jornada en curso.')) ?></p>
            <?php endif; ?>
        <?php elseif ((int)$cup['winner_team_id'] === (int)$myTeam['id']): ?>
            <p>🏆 <?= t('<strong>¡Sois los campeones!</strong> El trofeo ya está en tu vitrina.') ?></p>
        <?php else: ?>
            <p>❌ <?= e(t('Eliminado en {stage}.', ['stage' => mb_strtolower(CupService::stageName($stageOfRound[(int)$mine['eliminated_round']] ?? null))])) ?></p>
            <?php if ($myMatch): ?><p class="small"><a href="<?= e(url('match', ['id' => $myMatch['id']])) ?>"><?= e($myMatch['hn']) ?> <?= e(score_label($myMatch)) ?> <?= e($myMatch['an']) ?></a></p><?php endif; ?>
            <p class="small muted"><?= e(t('Ya puedes volver a jugar amistosos.')) ?></p>
        <?php endif; ?>
    </div></div>
</div>

<?php if ($rounds): ?>
<div class="box">
    <div class="box-head"><?= e(t('Rondas')) ?></div>
    <div class="box-body tabs-inline">
        <?php foreach (array_reverse($rounds) as $r): $rr = (int)$r['round']; ?>
            <a class="btn btn-small <?= $rr === $viewRound ? '' : 'btn-grey' ?>" href="<?= e(url('cup', ['country' => $countryId, 'season' => $season, 'round' => $rr])) ?>"><?= e(CupService::stageName($r['cup_stage'])) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($games): ?>
    <div class="box-head small"><?= e(CupService::stageName($games[0]['cup_stage'])) ?> · <?= e(fdate($games[0]['scheduled_at'])) ?> · <?= e(t('{n} partido(s)', ['n' => count($games)])) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <?php foreach ($games as $m): $played = $m['status'] === 'played';
            $isMine = $myTeam && in_array((int)$myTeam['id'], [(int)$m['home_team_id'], (int)$m['away_team_id']], true);
            $win = $played ? WorldCupService::winner($m) : 0; ?>
            <tr<?= $isMine ? ' class="highlight"' : '' ?>>
                <td class="right"><?= $win === (int)$m['home_team_id'] ? '<strong>' : '' ?><?= team_link($m['home_team_id'], $m['hn']) ?><?= $win === (int)$m['home_team_id'] ? '</strong>' : '' ?> <span class="small muted"><?= $m['hlvl'] ? LeagueService::roman((int)$m['hlvl']) : '' ?></span></td>
                <td class="center nowrap"><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><?= $played ? '<strong>' . e(score_label($m)) . '</strong>' : e(date('d/m H:i', strtotime($m['scheduled_at']))) ?></a></td>
                <td><span class="small muted"><?= $m['alvl'] ? LeagueService::roman((int)$m['alvl']) : '' ?></span> <?= $win === (int)$m['away_team_id'] ? '<strong>' : '' ?><?= team_link($m['away_team_id'], $m['an']) ?><?= $win === (int)$m['away_team_id'] ? '</strong>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Goleadores de la Copa')) ?></div>
        <?php if (!$scorers): ?><div class="box-body muted small"><?= e(t('Todavía no hay goles.')) ?></div><?php else: ?>
        <table class="table compact">
            <?php foreach ($scorers as $i => $s): ?>
                <tr><td><?= $i + 1 ?>.</td><td><?= player_link($s) ?></td><td class="small"><?= team_link($s['tid'], $s['tn']) ?></td><td class="num"><strong><?= (int)$s['g'] ?></strong></td></tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Palmarés')) ?></div>
        <?php if (!$history): ?><div class="box-body muted small"><?= e(t('Aún no se ha disputado ninguna final.')) ?></div><?php else: ?>
        <table class="table compact">
            <thead><tr><th><?= e(t('Temp.')) ?></th><th><?= e(t('Campeón')) ?></th><th><?= e(t('Subcampeón')) ?></th></tr></thead>
            <?php foreach ($history as $h): ?>
                <tr><td><?= (int)$h['season'] ?></td><td>🏆 <?= team_link($h['wid'], $h['wn']) ?></td><td><?= $h['rid'] ? team_link($h['rid'], $h['rn']) : '–' ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
