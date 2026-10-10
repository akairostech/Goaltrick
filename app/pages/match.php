<?php
require_login();
$id = (int)get('id');
$m = DB::row('SELECT m.*, h.name AS hn, a.name AS an, h.stadium_name, lu.name AS unit_name FROM matches m
    JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    LEFT JOIN league_units lu ON lu.id = m.league_unit_id WHERE m.id = ?', [$id]);
if (!$m) {
    flash('error', t('Partido no encontrado.'));
    redirect(url('matches'));
}
$pageTitle = $m['hn'] . ' - ' . $m['an'];
$navActive = 'league';
$played = $m['status'] === 'played';
$type = match ($m['type']) {
    'league' => t('Liga · Serie {unit} · Jornada {round}', ['unit' => $m['unit_name'], 'round' => (int)$m['round']]),
    'worldcup' => t('Mundial · {stage} · Campo neutral', ['stage' => WorldCupService::stageName($m['cup_stage'])]),
    default => t('Partido amistoso'),
};
?>
<div class="scoreboard">
    <div class="team home"><?= team_link($m['home_team_id'], $m['hn']) ?></div>
    <div class="score"><?= $played ? (int)$m['home_goals'] . ' - ' . (int)$m['away_goals'] : 'vs' ?><?php if ($played && $m['home_pens'] !== null): ?><div style="font-size:13px;font-family:Verdana,sans-serif">(<?= e(t('{home}-{away} pen.', ['home' => (int)$m['home_pens'], 'away' => (int)$m['away_pens']])) ?>)</div><?php endif; ?></div>
    <div class="team away"><?= team_link($m['away_team_id'], $m['an']) ?></div>
    <div class="meta"><?= e($type) ?> · <?= e(fdate_long($m['scheduled_at'])) ?><?= $m['type'] === 'worldcup' ? '' : ' · ' . e($m['stadium_name']) ?><?= $played ? ' · ' . e(t('{n} espectadores', ['n' => num($m['attendance'])])) : '' ?></div>
</div>

<?php if (!$played): ?>
    <div class="box"><div class="box-body center">
        <p><?= t('El partido aún no se ha jugado. Comienza en {time}.', ['time' => '<strong data-deadline="' . e(date('c', strtotime($m['scheduled_at']))) . '">' . e(time_left($m['scheduled_at'])) . '</strong>']) ?></p>
        <?php $my = current_team(); if ($my && in_array((int)$my['id'], [(int)$m['home_team_id'], (int)$m['away_team_id']], true)): ?>
            <a class="btn" href="<?= e(url('lineup')) ?>"><?= e(t('Preparar alineación')) ?></a>
        <?php endif; ?>
    </div></div>
<?php return; endif;

$ratings = json_decode((string)$m['ratings'], true) ?: [];
$events = json_decode((string)$m['report'], true) ?: [];
$poss = (int)$m['home_possession'];
$lineups = DB::all('SELECT mp.*, p.first_name, p.last_name, p.avatar, p.id FROM match_players mp JOIN players p ON p.id = mp.player_id WHERE mp.match_id = ?', [$id]);
$order = array_flip(array_keys(Lineup::POSITIONS));
usort($lineups, fn($a, $b) => ($order[$a['position']] ?? 99) <=> ($order[$b['position']] ?? 99));
$bySide = ['home' => [], 'away' => []];
foreach ($lineups as $l) {
    $bySide[(int)$l['team_id'] === (int)$m['home_team_id'] ? 'home' : 'away'][] = $l;
}
?>
<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Crónica del partido')) ?></div>
        <ul class="events">
            <?php foreach ($events as $ev): ?>
                <li class="ev-<?= e($ev['t']) ?> <?= e($ev['s'] ?? '') ?>">
                    <span class="min"><?= (int)$ev['m'] ?>'</span>
                    <span class="txt"><?= e(tdb($ev['x'])) ?><?= $ev['t'] === 'goal' ? ' <span class="tag">' . e($ev['sc']) . '</span>' : '' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="stack">
        <div class="box">
            <div class="box-head"><?= e(t('Estadísticas')) ?></div>
            <table class="table compact rating-cmp">
                <thead><tr><th class="right"><?= e($m['hn']) ?></th><th class="center"><?= e(t('Sector')) ?></th><th><?= e($m['an']) ?></th></tr></thead>
                <tbody>
                <tr><td class="right"><strong><?= $poss ?>%</strong></td><td class="center"><?= e(t('Posesión')) ?></td><td><strong><?= 100 - $poss ?>%</strong></td></tr>
                <tr><td class="right"><?= (int)($ratings['home']['chances'] ?? 0) ?></td><td class="center"><?= e(t('Ocasiones')) ?></td><td><?= (int)($ratings['away']['chances'] ?? 0) ?></td></tr>
                <tr><td class="right"><?= e(t(TACTICS[$ratings['home']['tactic'] ?? 'normal'] ?? '')) ?></td><td class="center"><?= e(t('Táctica')) ?></td><td><?= e(t(TACTICS[$ratings['away']['tactic'] ?? 'normal'] ?? '')) ?></td></tr>
                <?php foreach (MatchEngine::SECTORS as $k => $label):
                    $h = (float)($ratings['home'][$k] ?? 0);
                    $a = (float)($ratings['away'][$k] ?? 0);
                ?>
                    <tr>
                        <td title="<?= e(rating_name($h)) ?>"><div class="rbar home"><span style="width:<?= min(100, $h / 20 * 100) ?>%"></span></div><div class="small right"><?= e(rating_name($h)) ?></div></td>
                        <td class="center small nowrap"><?= e(t($label)) ?></td>
                        <td title="<?= e(rating_name($a)) ?>"><div class="rbar away"><span style="width:<?= min(100, $a / 20 * 100) ?>%"></span></div><div class="small"><?= e(rating_name($a)) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="small muted" style="padding:0 10px"><?= e(t('La defensa derecha de un equipo se enfrenta al ataque izquierdo del rival y viceversa.')) ?></p>
        </div>
        <?php foreach (['home' => $m['hn'], 'away' => $m['an']] as $side => $name): ?>
        <div class="box">
            <div class="box-head"><?= e(t('Alineación de {team}', ['team' => $name])) ?></div>
            <table class="table compact">
                <thead><tr><th><?= e(t('Pos')) ?></th><th><?= e(t('Jugador')) ?></th><th class="num"><?= e(t('Min')) ?></th><th><?= e(t('Valoración')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($bySide[$side] as $l): ?>
                    <tr>
                        <td class="small"><?= e(isset(Lineup::POSITIONS[$l['position']][2]) ? t(Lineup::POSITIONS[$l['position']][2]) : $l['position']) ?></td>
                        <td class="nowrap"><?= avatar_img($l, 20) ?> <?= player_link($l) ?><?= $l['goals'] ? ' ⚽' . ($l['goals'] > 1 ? '×' . (int)$l['goals'] : '') : '' ?><?= $l['red'] ? ' 🟥' : ($l['yellow'] ? ' 🟨' : '') ?><?= $l['injured'] ? ' 🩹' : '' ?></td>
                        <td class="num"><?= (int)$l['minutes'] ?></td>
                        <td><?= stars($l['stars']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</div>
