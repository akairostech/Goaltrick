<?php
require_login();
$id = (int)get('id');
$m = DB::row('SELECT m.*, h.name AS hn, a.name AS an, h.stadium_name, lu.name AS unit_name,
    h.crest_id AS h_crest_id, h.crest_custom AS h_crest_custom, a.crest_id AS a_crest_id, a.crest_custom AS a_crest_custom,
    h.is_national AS h_nat, a.is_national AS a_nat, h.country_id AS h_country, a.country_id AS a_country FROM matches m
    JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    LEFT JOIN league_units lu ON lu.id = m.league_unit_id WHERE m.id = ?', [$id]);
if (!$m) {
    flash('error', t('Partido no encontrado.'));
    redirect(url('matches'));
}
$pageTitle = $m['hn'] . ' - ' . $m['an'];
$navActive = 'league';
$played = $m['status'] === 'played';
$crestH = ['id' => $m['home_team_id'], 'crest_id' => $m['h_crest_id'], 'crest_custom' => $m['h_crest_custom']];
$crestA = ['id' => $m['away_team_id'], 'crest_id' => $m['a_crest_id'], 'crest_custom' => $m['a_crest_custom']];
$teamLink = fn(string $side) => (int)$m[$side[0] . '_nat']
    ? '<a href="' . e(url('nation', ['id' => $m[$side[0] . '_country']])) . '">' . e($m[$side === 'home' ? 'hn' : 'an']) . '</a>'
    : team_link($m[$side . '_team_id'], $m[$side === 'home' ? 'hn' : 'an']);
$type = match ($m['type']) {
    'league' => t('Liga · Serie {unit} · Jornada {round}', ['unit' => $m['unit_name'], 'round' => (int)$m['round']]),
    'worldcup' => t('Mundial · {stage} · Campo neutral', ['stage' => WorldCupService::stageName($m['cup_stage'])]),
    'cup' => t('Copa · {stage}', ['stage' => CupService::stageName($m['cup_stage'])]),
    default => t('Partido amistoso'),
};
?>
<div class="scoreboard">
    <div class="team home"><?= $teamLink('home') ?> <?= crest_img($crestH, 46, 'crest sb-crest') ?></div>
    <div class="score"><?= $played ? (int)$m['home_goals'] . ' - ' . (int)$m['away_goals'] : 'vs' ?><?php if ($played && $m['home_pens'] !== null): ?><div style="font-size:13px;font-family:Verdana,sans-serif">(<?= e(t('{home}-{away} pen.', ['home' => (int)$m['home_pens'], 'away' => (int)$m['away_pens']])) ?>)</div><?php endif; ?></div>
    <div class="team away"><?= crest_img($crestA, 46, 'crest sb-crest') ?> <?= $teamLink('away') ?></div>
    <div class="meta"><?= e($type) ?> · <?= e(fdate_long($m['scheduled_at'])) ?><?= $m['type'] === 'worldcup' ? '' : ' · ' . e($m['stadium_name']) ?><?= $played ? ' · ' . e(t('{n} espectadores', ['n' => num($m['attendance'])])) : '' ?></div>
</div>

<?php if (!$played):
    $my = current_team();
    $pv = MatchPreview::build($m, $my ? (int)$my['id'] : null);
    $sides = ['home', 'away'];
    $names = ['home' => $m['hn'], 'away' => $m['an']];
    $crests = ['home' => $crestH, 'away' => $crestA];
    $pct = fn(float $v) => (int)round($v * 100);
?>
    <div class="box"><div class="box-body center">
        <p><?= t('El partido aún no se ha jugado. Comienza en {time}.', ['time' => '<strong data-deadline="' . e(date('c', strtotime($m['scheduled_at']))) . '">' . e(time_left($m['scheduled_at'])) . '</strong>']) ?></p>
        <?php if ($my && in_array((int)$my['id'], [(int)$m['home_team_id'], (int)$m['away_team_id']], true)): ?>
            <a class="btn" href="<?= e(url('lineup')) ?>"><?= e(t('Preparar alineación')) ?></a>
        <?php endif; ?>
    </div></div>

    <div class="box preview-index">
        <div class="box-head">📊 <?= e(t('Índice de rendimiento estimado')) ?></div>
        <div class="box-body">
            <div class="pi-top">
                <?php foreach ($sides as $sd): ?>
                    <div class="pi-team pi-<?= $sd ?>">
                        <?= crest_img($crests[$sd], 40) ?>
                        <div><div class="pi-name"><?= e($names[$sd]) ?></div>
                            <div class="pi-big"><?= $pv['index'][$sd] ?><small>/100</small></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pi-overall"><span class="pi-bar-h" style="width:<?= round($pv['index']['home'] / max(1, $pv['index']['home'] + $pv['index']['away']) * 100, 1) ?>%"></span></div>
            <table class="pi-chart">
                <?php foreach (MatchEngine::SECTORS as $k => $label): $vh = $pv['ratings']['home'][$k]; $va = $pv['ratings']['away'][$k]; ?>
                    <tr>
                        <td class="pi-val"><?= e(rating_name($vh)) ?></td>
                        <td class="pi-bar left"><span style="width:<?= min(100, round($vh * 5, 1)) ?>%" class="<?= $vh >= $va ? 'lead' : '' ?>"></span></td>
                        <td class="pi-label"><?= e(t($label)) ?></td>
                        <td class="pi-bar right"><span style="width:<?= min(100, round($va * 5, 1)) ?>%" class="<?= $va > $vh ? 'lead' : '' ?>"></span></td>
                        <td class="pi-val right"><?= e(rating_name($va)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="small muted"><?= e(t('Estimación con la alineación que enviaste (o la predeterminada) para tu equipo y con la mejor alineación disponible para el rival. Incluye el factor campo, el espíritu y la confianza de cada equipo.')) ?></p>
        </div>
    </div>

    <div class="grid-2">
        <div class="box">
            <div class="box-head">🧾 <?= e(t('Resumen de los equipos')) ?></div>
            <div class="table-wrap">
            <table class="table compact preview-cmp">
                <thead><tr><th class="right"><?= crest_img($crestH, 20) ?> <?= e($m['hn']) ?></th><th class="center"></th><th><?= crest_img($crestA, 20) ?> <?= e($m['an']) ?></th></tr></thead>
                <?php
                $S = $pv['summary'];
                $T = $pv['teams'];
                $row = function (string $label, string $h, string $a) {
                    echo '<tr><td class="right">' . $h . '</td><th class="center small">' . e($label) . '</th><td>' . $a . '</td></tr>';
                };
                $form = function (array $f) {
                    if (!$f) {
                        return '<span class="muted">–</span>';
                    }
                    $h = '';
                    foreach (array_reverse($f) as $x) {
                        $h .= '<a class="res res-' . $x['r'] . '" href="' . e(url('match', ['id' => $x['id']])) . '" title="' . e($x['txt']) . '">'
                            . e($x['r'] === 'w' ? t('G##ganado') : ($x['r'] === 'l' ? t('P##perdido') : t('E##empate'))) . '</a>';
                    }
                    return $h;
                };
                $mgr = fn(array $s) => $s['manager'] !== null ? e($s['manager']) : '<span class="tag tag-bot">' . e(t('Máquina')) . '</span>';
                $row(t('Mánager'), $mgr($S['home']), $mgr($S['away']));
                if ($S['home']['unit'] || $S['away']['unit']) {
                    $lg = fn(array $s) => $s['unit'] ? e(t('Serie {name}', ['name' => $s['unit']['name']])) . ($s['pos'] ? ' · <strong>' . $s['pos'] . 'º</strong> (' . e(t('{n} pts', ['n' => $s['pts']])) . ')' : '') : '–';
                    $row(t('Liga'), $lg($S['home']), $lg($S['away']));
                    $gl = fn(array $s) => $s['gf'] !== null ? $s['gf'] . ' : ' . $s['gc'] : '–';
                    $row(t('Goles (liga)'), $gl($S['home']), $gl($S['away']));
                }
                $row(t('Últimos 5'), $form($S['home']['form']), $form($S['away']['form']));
                $row(t('Jugadores'), (string)$S['home']['players'] . ($S['home']['injured'] ? ' <span class="small neg">(' . e(t('{n} bajas', ['n' => $S['home']['injured']])) . ')</span>' : ''), (string)$S['away']['players'] . ($S['away']['injured'] ? ' <span class="small neg">(' . e(t('{n} bajas', ['n' => $S['away']['injured']])) . ')</span>' : ''));
                $row(t('Edad media'), num($S['home']['age'], 1), num($S['away']['age'], 1));
                $row(t('TSI total'), num($S['home']['tsi']), num($S['away']['tsi']));
                $row(t('Forma media'), e(form_name($S['home']['pform'])), e(form_name($S['away']['pform'])));
                $row(t('Espíritu'), e(mood_name($T['home']['team_spirit'], 'spirit')), e(mood_name($T['away']['team_spirit'], 'spirit')));
                $row(t('Confianza'), e(mood_name($T['home']['confidence'], 'confidence')), e(mood_name($T['away']['confidence'], 'confidence')));
                if (!(int)$T['home']['is_national']) {
                    $row(t('Socios'), num($T['home']['fans']), num($T['away']['fans']));
                    $tr = fn(array $s) => !empty($s['trainer']) ? StaffService::link($s['trainer']) . ' <span class="small muted">(' . (int)$s['trainer']['level'] . ')</span>' : '–';
                    $row(t('Entrenador'), $tr($S['home']), $tr($S['away']));
                }
                $sc = fn(array $s) => $s['scorer'] ? player_link($s['scorer']) . ' <span class="small muted">(' . $s['scorer']['g'] . ')</span>' : '–';
                $row(t('Máximo goleador'), $sc($S['home']), $sc($S['away']));
                ?>
            </table>
            </div>
        </div>
        <div class="box">
            <div class="box-head">🔮 <?= e(t('Pronóstico')) ?></div>
            <div class="box-body">
                <div class="prob-bar">
                    <span class="pb-home" style="width:<?= $pct($pv['probs']['home']) ?>%"><?= $pct($pv['probs']['home']) ?>%</span><span class="pb-draw" style="width:<?= $pct($pv['probs']['draw']) ?>%"><?= $pct($pv['probs']['draw']) ?>%</span><span class="pb-away" style="width:<?= max(0, 100 - $pct($pv['probs']['home']) - $pct($pv['probs']['draw'])) ?>%"><?= max(0, 100 - $pct($pv['probs']['home']) - $pct($pv['probs']['draw'])) ?>%</span>
                </div>
                <div class="prob-legend small"><span><?= e(t('Gana {team}', ['team' => $m['hn']])) ?></span><span><?= e(t('Empate')) ?></span><span><?= e(t('Gana {team}', ['team' => $m['an']])) ?></span></div>
                <p class="center"><?= e(t('Marcador más probable')) ?>: <strong class="big-score"><?= $pv['score'][0] ?> - <?= $pv['score'][1] ?></strong><br>
                    <span class="small muted"><?= e(t('Goles esperados: {h} - {a}', ['h' => num($pv['xg']['home'], 1), 'a' => num($pv['xg']['away'], 1)])) ?></span></p>
                <?php if ($pv['knockout']): ?><p class="small muted center"><?= e(t('Eliminatoria: si hay empate se decide en la tanda de penaltis.')) ?></p><?php endif; ?>
            </div>
            <div class="box-head">📣 <?= e(t('¿Qué piensan los aficionados?')) ?></div>
            <div class="box-body fans-poll">
                <?php foreach ($sides as $sd): $f = $pv['fans'][$sd]; ?>
                    <div class="fp">
                        <div class="fp-head"><?= crest_img($crests[$sd], 26) ?> <strong><?= e(t('Socios de {team}', ['team' => $names[$sd]])) ?></strong>
                            <span class="small muted"><?= e(t('{n} encuestados', ['n' => num($f['voters'])])) ?></span></div>
                        <div class="fp-quote"><?= e($f['quote']) ?></div>
                        <div class="prob-bar small-bar">
                            <span class="pb-win" style="width:<?= $pct($f['win']) ?>%"><?= $pct($f['win']) ?>%</span><span class="pb-draw" style="width:<?= $pct($f['draw']) ?>%"><?= $pct($f['draw']) ?>%</span><span class="pb-lose" style="width:<?= max(0, 100 - $pct($f['win']) - $pct($f['draw'])) ?>%"><?= max(0, 100 - $pct($f['win']) - $pct($f['draw'])) ?>%</span>
                        </div>
                        <div class="small"><?= e(t('Victoria / empate / derrota')) ?> · <?= e(t('Resultado más votado')) ?>: <strong><?= e($m['hn']) ?> <?= $f['score'][0] ?> - <?= $f['score'][1] ?> <?= e($m['an']) ?></strong></div>
                    </div>
                <?php endforeach; ?>
                <p class="small muted"><?= e(t('Los socios son más optimistas cuanto mejor es el ánimo de la afición y la confianza del equipo.')) ?></p>
            </div>
        </div>
    </div>
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
