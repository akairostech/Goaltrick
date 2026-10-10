<?php
$team = require_team();
$pageTitle = 'Alineación';
$navActive = 'club';
$teamId = (int)$team['id'];

$players = DB::all("SELECT * FROM players WHERE team_id = ? AND retired = 0 ORDER BY FIELD(role,'GK','CD','WB','IM','W','FW'), tsi DESC", [$teamId]);
$byId = [];
foreach ($players as $p) {
    $byId[(int)$p['id']] = $p;
}

if (is_post()) {
    csrf_check();
    if (post('auto')) {
        $formation = (string)post('formation', '4-4-2');
        $lu = Lineup::auto($teamId, isset(Lineup::FORMATIONS[$formation]) ? $formation : '4-4-2');
        $old = Lineup::get($teamId);
        $lu['tactic'] = $old['tactic'];
        $lu['attitude'] = $old['attitude'];
        Lineup::save($teamId, $lu);
        flash('success', "Alineación automática ($formation) generada con los jugadores disponibles.");
        redirect(url('lineup'));
    }
    $positions = [];
    $orders = [];
    $used = [];
    $errors = [];
    $starters = 0;
    foreach (array_merge(array_keys(Lineup::POSITIONS), array_keys(Lineup::BENCH)) as $slot) {
        $pid = (int)($_POST['pos'][$slot] ?? 0);
        if (!$pid) {
            continue;
        }
        if (!isset($byId[$pid])) {
            $errors[] = 'Jugador no válido.';
            continue;
        }
        if (isset($used[$pid])) {
            $errors[] = player_name($byId[$pid]) . ' está en más de una posición.';
            continue;
        }
        $used[$pid] = true;
        $positions[$slot] = $pid;
        if (isset(Lineup::POSITIONS[$slot])) {
            $starters++;
            $o = (string)($_POST['order'][$slot] ?? 'normal');
            if ($o !== 'normal' && isset(Lineup::ORDERS[$o]) && $slot !== 'GK') {
                $orders[$slot] = $o;
            }
        }
    }
    if ($starters > 11) {
        $errors[] = 'No puedes alinear más de 11 jugadores.';
    }
    if (!$errors) {
        $tactic = (string)post('tactic');
        $attitude = (string)post('attitude');
        $cap = (int)post('captain');
        $sp = (int)post('setpieces');
        Lineup::save($teamId, [
            'positions'    => $positions,
            'orders'       => $orders,
            'tactic'       => isset(TACTICS[$tactic]) ? $tactic : 'normal',
            'attitude'     => isset(ATTITUDES[$attitude]) ? $attitude : 'normal',
            'captain_id'   => isset($used[$cap]) ? $cap : null,
            'setpieces_id' => isset($used[$sp]) ? $sp : null,
        ]);
        $msg = 'Alineación guardada.';
        if ($starters < 11) {
            flash('warning', "Sólo has alineado $starters titulares. Los huecos se cubrirán con suplentes si es posible.");
        } elseif (!isset($positions['GK'])) {
            flash('warning', 'No has alineado portero.');
        }
        flash('success', $msg);
    } else {
        foreach (array_unique($errors) as $er) {
            flash('error', $er);
        }
    }
    redirect(url('lineup'));
}

$lu = Lineup::get($teamId);
if (!$lu['positions']) {
    $lu = Lineup::auto($teamId);
}
$next = LeagueService::nextMatch($teamId);
$preview = MatchEngine::preview(DB::row('SELECT * FROM teams WHERE id = ?', [$teamId]));

$rows = [
    [null, null, 'GK', null, null],
    ['RB', 'RCD', 'CD', 'LCD', 'LB'],
    ['RW', 'RIM', 'IM', 'LIM', 'LW'],
    [null, 'RFW', 'FW', 'LFW', null],
];

// Datos de cada jugador para las tarjetas, el arrastre y el tooltip
$data = [];
foreach ($players as $p) {
    $fit = [];
    foreach (array_keys(Lineup::POSITIONS) as $slot) {
        $fit[$slot] = round(Lineup::suitability($p, $slot), 1);
    }
    $skills = [];
    foreach (SKILLS as $k => $label) {
        $skills[] = [$label, (int)floor((float)$p[$k]), skill_name($p[$k])];
    }
    $data[(int)$p['id']] = [
        'id'     => (int)$p['id'],
        'name'   => player_name($p),
        'short'  => $p['last_name'],
        'img'    => avatar_url($p),
        'role'   => $p['role'],
        'roleName' => ROLES[$p['role']] ?? $p['role'],
        'age'    => (int)$p['age_years'],
        'days'   => (int)$p['age_days'],
        'tsi'    => num($p['tsi']),
        'form'   => (int)floor((float)$p['form']),
        'formName' => form_name($p['form']),
        'stamina'=> (int)floor((float)$p['stamina']),
        'xp'     => (int)floor((float)$p['experience']),
        'lead'   => (int)$p['leadership'],
        'spec'   => $p['specialty'] ? SPECIALTIES[$p['specialty']][1] . ' ' . SPECIALTIES[$p['specialty']][0] : '',
        'inj'    => (float)$p['injury_weeks'],
        'susp'   => (int)$p['suspended'],
        'yc'     => (int)$p['yellow_cards'],
        'goals'  => (int)$p['goals_season'],
        'skills' => $skills,
        'fit'    => $fit,
    ];
}
$slotNames = [];
foreach (Lineup::POSITIONS as $slot => $def) {
    $slotNames[$slot] = $def[2];
}
foreach (Lineup::BENCH_LABELS as $slot => $label) {
    $slotNames[$slot] = $label;
}
$slotHtml = function (string $slot, bool $bench = false) use ($lu, $slotNames) {
    $pid = isset($lu['positions'][$slot]) ? (int)$lu['positions'][$slot] : 0;
    $h = '<div class="lslot drop' . ($bench ? ' bench-slot' : '') . '" data-slot="' . e($slot) . '">'
        . '<div class="lslot-label">' . e($bench ? str_replace(' suplente', '', $slotNames[$slot]) : $slotNames[$slot]) . '</div>'
        . '<input type="hidden" name="pos[' . e($slot) . ']" value="' . ($pid ?: '') . '">'
        . '<div class="lslot-body"></div>';
    if (!$bench && $slot !== 'GK') {
        $h .= '<select class="order" name="order[' . e($slot) . ']" title="Orden individual">';
        foreach (Lineup::ORDERS as $ok => $ol) {
            $h .= '<option value="' . e($ok) . '"' . (($lu['orders'][$slot] ?? 'normal') === $ok ? ' selected' : '') . '>' . e($ol) . '</option>';
        }
        $h .= '</select>';
    }
    return $h . '</div>';
};
?>
<div class="page-title">
    <h1>Alineación</h1>
    <?php if ($next): ?><span class="muted">Próximo partido: <strong><?= e($next['home_name']) ?> vs <?= e($next['away_name']) ?></strong> · <?= e(fdate_long($next['scheduled_at'])) ?></span><?php endif; ?>
</div>

<div class="box">
    <div class="box-body">
        <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="auto" value="1">
            <label for="formation" style="margin:0">Alineación automática:</label>
            <select name="formation" id="formation">
                <?php foreach (array_keys(Lineup::FORMATIONS) as $f): ?><option><?= e($f) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-grey" type="submit">Generar con los mejores disponibles</button>
        </form>
    </div>
</div>

<form method="post" id="lineup-form" class="lineup-dnd">
    <?= csrf_field() ?>
    <script type="application/json" id="lineup-data"><?= json_encode(['players' => $data, 'slots' => $slotNames], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <div class="lineup-layout">
        <div class="box">
            <div class="box-head">Titulares <span>Formación: <strong id="formation-label">–</strong> · Alineados: <strong id="starter-count">0</strong>/11</span></div>
            <div class="box-body">
                <div class="lineup-msg" id="lineup-msg" hidden></div>
                <div class="pitch pitch-dnd">
                    <div class="pitch-lines"></div>
                    <?php foreach ($rows as $row): ?>
                        <div class="pitch-row">
                            <?php foreach ($row as $slot): ?>
                                <?= $slot === null ? '<div class="lslot-gap"></div>' : $slotHtml($slot) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="bench-title">Banquillo <span class="small muted">— si un titular se lesiona o no puede jugar, entra el suplente de su línea</span></div>
                <div class="bench-dnd">
                    <?php foreach (array_keys(Lineup::BENCH_LABELS) as $slot): ?><?= $slotHtml($slot, true) ?><?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="box squad-box">
            <div class="box-head">Plantilla <span class="small"><?= count($players) ?> jugadores</span></div>
            <div class="squad-filter">
                <button type="button" class="active" data-filter="all">Todos</button>
                <button type="button" data-filter="free">Sin alinear</button>
                <?php foreach (['GK', 'CD', 'WB', 'IM', 'W', 'FW'] as $r): ?><button type="button" data-filter="<?= $r ?>"><?= $r ?></button><?php endforeach; ?>
            </div>
            <div class="squad-list drop-out" id="squad-list"></div>
            <div class="box-body small muted">🖱 Arrastra a los jugadores al campo o al banquillo. Suelta uno sobre otro para intercambiarlos y arrástralo de vuelta a la lista (o haz doble clic) para quitarlo. También puedes hacer clic en un jugador y luego en la posición. Pasa el ratón por encima para ver sus datos.</div>
        </div>
    </div>

    <div class="grid-2">
        <div class="box">
            <div class="box-head">Táctica</div>
            <div class="box-body">
                <div class="field">
                    <label>Táctica</label>
                    <select name="tactic"><?php foreach (TACTICS as $k => $v): ?><option value="<?= e($k) ?>"<?= $lu['tactic'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                </div>
                <div class="field">
                    <label>Actitud</label>
                    <select name="attitude"><?php foreach (ATTITUDES as $k => $v): ?><option value="<?= e($k) ?>"<?= $lu['attitude'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    <div class="hint">PIC: juegas peor pero mejora el espíritu. MOTS: juegas mejor pero baja el espíritu.</div>
                </div>
            </div>
        </div>
        <div class="box">
            <div class="box-head">Capitán y balón parado</div>
            <div class="box-body">
                <div class="form-row">
                    <div class="field">
                        <label>Capitán</label>
                        <select name="captain" id="captain-select"><option value="">—</option>
                            <?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$lu['captain_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e(player_name($p)) ?> (lid. <?= (int)$p['leadership'] ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Lanzador de faltas</label>
                        <select name="setpieces" id="setpieces-select"><option value="">—</option>
                            <?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$lu['setpieces_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e(player_name($p)) ?> (BP <?= (int)floor((float)$p['set_pieces']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="hint">Sólo puedes elegir jugadores que estén alineados (titulares o suplentes).</div>
                <button class="btn btn-block btn-accent" type="submit">💾 Guardar alineación</button>
            </div>
        </div>
    </div>
</form>
<div id="player-tip" class="player-tip" hidden></div>
<br>
<div class="box">
    <div class="box-head">Valoraciones estimadas (con la alineación guardada, sin factor campo)</div>
    <table class="table compact">
        <tbody>
        <?php foreach (MatchEngine::SECTORS as $k => $label): ?>
            <tr><td style="width:180px"><?= e($label) ?></td><td><?= skill_bar($preview[$k]) ?> <?= e(rating_name($preview[$k])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small">Consejos: tres centrales, mediocentros o delanteros en la misma línea rinden algo menos cada uno (se estorban). Las órdenes ofensivas suben el ataque y bajan la defensa del jugador, y al revés con las defensivas. Mientras arrastras a un jugador, las posiciones se colorean según lo bien que rendiría en cada una.</p>
