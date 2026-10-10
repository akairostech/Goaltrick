<?php
$team = require_team();
$pageTitle = t('Alineación');
$navActive = 'club';
$teamId = (int)$team['id'];

$players = DB::all("SELECT * FROM players WHERE team_id = ? AND retired = 0 ORDER BY FIELD(role,'GK','CD','WB','IM','W','FW'), tsi DESC", [$teamId]);
$byId = [];
foreach ($players as $p) {
    $byId[(int)$p['id']] = $p;
}

// Partido para el que se prepara la alineación: ?match=ID o, si no, el próximo
$upcoming = LeagueService::teamMatches($teamId, "status = 'scheduled'", [], 'x.scheduled_at', 40);
$wanted = (int)get('match');
$next = null;
foreach ($upcoming as $u) {
    if ((int)$u['id'] === $wanted) {
        $next = $u;
    }
}
$next = $next ?? ($upcoming[0] ?? null);
$nextId = $next ? (int)$next['id'] : null;
$self = url('lineup', $nextId ? ['match' => $nextId] : []);

/** Lee la alineación enviada en el formulario. Devuelve [alineación, errores, titulares] */
$parse = function () use ($byId): array {
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
            $errors[] = t('Jugador no válido.');
            continue;
        }
        if (isset($used[$pid])) {
            $errors[] = t('{name} está en más de una posición.', ['name' => player_name($byId[$pid])]);
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
        $errors[] = t('No puedes alinear más de 11 jugadores.');
    }
    $tactic = (string)post('tactic');
    $attitude = (string)post('attitude');
    $cap = (int)post('captain');
    $sp = (int)post('setpieces');
    return [[
        'positions'    => $positions,
        'orders'       => $orders,
        'tactic'       => isset(TACTICS[$tactic]) ? $tactic : 'normal',
        'attitude'     => isset(ATTITUDES[$attitude]) ? $attitude : 'normal',
        'captain_id'   => isset($used[$cap]) ? $cap : null,
        'setpieces_id' => isset($used[$sp]) ? $sp : null,
    ], $errors, $starters];
};

if (is_post()) {
    csrf_check();
    $action = (string)post('action', 'send');
    if (post('auto')) {
        $formation = (string)post('formation', '4-4-2');
        $formation = isset(Lineup::FORMATIONS[$formation]) ? $formation : '4-4-2';
        $lu = Lineup::auto($teamId, $formation);
        $old = Lineup::get($teamId);
        $lu['tactic'] = $old['tactic'];
        $lu['attitude'] = $old['attitude'];
        Lineup::save($teamId, $lu, $nextId);
        flash('success', t('Alineación automática ({f}) generada con los jugadores disponibles.', ['f' => $formation]));
        redirect($self);
    }
    if ($action === 'delete_preset') {
        $slot = (int)post('slot');
        Lineup::deletePreset($teamId, $slot);
        flash('success', $slot === 0 ? t('Alineación predeterminada eliminada.') : t('Alineación guardada eliminada.'));
        redirect($self);
    }
    [$lineup, $errors, $starters] = $parse();
    if ($errors) {
        foreach (array_unique($errors) as $er) {
            flash('error', $er);
        }
        redirect($self);
    }
    if (!$lineup['positions']) {
        flash('error', t('La alineación está vacía.'));
        redirect($self);
    }
    if ($action === 'default') {
        Lineup::savePreset($teamId, 0, t('Predeterminada'), $lineup);
        flash('success', t('Alineación predeterminada guardada. Se usará en los partidos para los que no envíes alineación.'));
    } elseif ($action === 'preset') {
        $slot = max(1, min(Lineup::PRESET_SLOTS, (int)post('slot', 1)));
        $name = trim((string)post('preset_name'));
        if ($name === '') {
            $name = t('Alineación {n}', ['n' => $slot]);
        }
        Lineup::savePreset($teamId, $slot, $name, $lineup);
        flash('success', t('Alineación guardada como «{name}».', ['name' => mb_substr($name, 0, 40)]));
    } else {
        Lineup::save($teamId, $lineup, $nextId);
        if ($starters < 11) {
            flash('warning', t('Sólo has alineado {n} titulares. Los huecos se cubrirán con suplentes si es posible.', ['n' => $starters]));
        } elseif (!isset($lineup['positions']['GK'])) {
            flash('warning', t('No has alineado portero.'));
        }
        flash('success', $next ? t('Alineación enviada para el partido {home} - {away}.', ['home' => $next['home_name'], 'away' => $next['away_name']]) : t('Alineación guardada.'));
    }
    redirect($self);
}

$presets = Lineup::presets($teamId);
$sent = Lineup::get($teamId);
$sentForNext = $nextId && Lineup::sentFor($teamId, $nextId) !== null;
$lu = $nextId ? Lineup::planned($teamId, $nextId) : $sent;
if (!$lu['positions']) {
    $lu = Lineup::auto($teamId);
}
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
        $skills[] = [skill_title($k), (int)floor((float)$p[$k]), skill_name($p[$k])];
    }
    $data[(int)$p['id']] = [
        'id'       => (int)$p['id'],
        'name'     => player_name($p),
        'short'    => $p['last_name'],
        'img'      => avatar_url($p),
        'role'     => $p['role'],
        'roleName' => role_name($p['role']),
        'age'      => (int)$p['age_years'],
        'days'     => (int)$p['age_days'],
        'tsi'      => num($p['tsi']),
        'form'     => (int)floor((float)$p['form']),
        'formName' => form_name($p['form']),
        'stamina'  => (int)floor((float)$p['stamina']),
        'xp'       => (int)floor((float)$p['experience']),
        'lead'     => (int)$p['leadership'],
        'spec'     => $p['specialty'] ? SPECIALTIES[$p['specialty']][1] . ' ' . specialty_name($p['specialty']) : '',
        'inj'      => (float)$p['injury_weeks'],
        'susp'     => (int)$p['suspended'],
        'yc'       => (int)$p['yellow_cards'],
        'goals'    => (int)$p['goals_season'],
        'skills'   => $skills,
        'fit'      => $fit,
    ];
}
$slotNames = [];
foreach (Lineup::POSITIONS as $slot => $def) {
    $slotNames[$slot] = t($def[2]);
}
foreach (Lineup::BENCH_LABELS as $slot => $label) {
    $slotNames[$slot] = t($label);
}
$slotHtml = function (string $slot, bool $bench = false) use ($lu, $slotNames) {
    $pid = isset($lu['positions'][$slot]) ? (int)$lu['positions'][$slot] : 0;
    $h = '<div class="lslot drop' . ($bench ? ' bench-slot' : '') . '" data-slot="' . e($slot) . '">'
        . '<div class="lslot-label">' . e($bench ? role_name(Lineup::BENCH[$slot]) : $slotNames[$slot]) . '</div>'
        . '<input type="hidden" name="pos[' . e($slot) . ']" value="' . ($pid ?: '') . '">'
        . '<div class="lslot-body"></div>';
    if (!$bench && $slot !== 'GK') {
        $h .= '<select class="order" name="order[' . e($slot) . ']" title="' . e(t('Orden individual')) . '">';
        foreach (Lineup::ORDERS as $ok => $ol) {
            $h .= '<option value="' . e($ok) . '"' . (($lu['orders'][$slot] ?? 'normal') === $ok ? ' selected' : '') . '>' . e(t($ol)) . '</option>';
        }
        $h .= '</select>';
    }
    return $h . '</div>';
};
// Alineaciones guardadas para cargarlas en el editor
$presetData = [];
foreach ($presets as $slot => $pr) {
    $presetData[$slot] = $pr['lineup'];
}
$presetLabel = fn(int $slot) => $slot === 0 ? t('Predeterminada') : ($presets[$slot]['name'] ?? t('Alineación {n}', ['n' => $slot]));
?>
<div class="page-title">
    <h1><?= e(t('Alineación')) ?></h1>
    <?php if ($next): ?><span class="muted"><?= e(t('Partido:')) ?> <strong><?= e($next['home_name']) ?> <?= e(t('vs')) ?> <?= e($next['away_name']) ?></strong> · <?= e(fdate_long($next['scheduled_at'])) ?></span><?php endif; ?>
</div>

<?php $picker = array_values(array_filter($upcoming, fn($u, $i) => $i < 6 || (int)$u['id'] === $nextId, ARRAY_FILTER_USE_BOTH));
if (count($picker) > 1): ?>
<div class="match-picker">
    <span class="small muted"><?= e(t('Preparar la alineación para:')) ?></span>
    <?php foreach ($picker as $u): $isSent = Lineup::sentFor($teamId, (int)$u['id']) !== null; $rival = (int)$u['home_team_id'] === $teamId ? ['id' => $u['away_team_id'], 'crest_id' => $u['a_crest_id'], 'crest_custom' => $u['a_crest_custom'], 'n' => $u['away_name']] : ['id' => $u['home_team_id'], 'crest_id' => $u['h_crest_id'], 'crest_custom' => $u['h_crest_custom'], 'n' => $u['home_name']]; ?>
        <a class="mp-item<?= (int)$u['id'] === $nextId ? ' on' : '' ?>" href="<?= e(url('lineup', ['match' => $u['id']])) ?>">
            <?= crest_img($rival, 22) ?>
            <span><strong><?= e($rival['n']) ?></strong><br><span class="small muted"><?= e(match_type_label($u)) ?> · <?= e(date('d/m H:i', strtotime($u['scheduled_at']))) ?></span></span>
            <span class="mp-state" title="<?= e($isSent ? t('Alineación enviada') : t('Sin enviar: se usará la predeterminada')) ?>"><?= $isSent ? '✅' : '⭐' ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($next): ?>
    <?php if ($sentForNext): ?>
        <div class="alert alert-success">✅ <?= e(t('Ya has enviado la alineación para este partido ({type}). Puedes cambiarla hasta que empiece.', ['type' => match_type_label($next)])) ?></div>
    <?php elseif (isset($presets[0])): ?>
        <div class="alert alert-info">📋 <?= e(t('Todavía no has enviado alineación para este partido: si no lo haces se usará tu alineación predeterminada (es la que ves cargada abajo).')) ?></div>
    <?php else: ?>
        <div class="alert alert-warning">⚠ <?= e(t('Todavía no has enviado alineación para este partido y no tienes alineación predeterminada: se usará la última que enviaste. Guarda una predeterminada para no tener que preocuparte.')) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="box">
    <div class="box-body lineup-tools">
        <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="auto" value="1">
            <label for="formation" style="margin:0"><?= e(t('Alineación automática:')) ?></label>
            <select name="formation" id="formation">
                <?php foreach (array_keys(Lineup::FORMATIONS) as $f): ?><option><?= e($f) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-grey" type="submit"><?= e(t('Generar con los mejores disponibles')) ?></button>
        </form>
        <div class="preset-load">
            <span class="small muted"><?= e(t('Cargar en el editor:')) ?></span>
            <?php for ($slot = 0; $slot <= Lineup::PRESET_SLOTS; $slot++): ?>
                <?php if (isset($presets[$slot])): ?>
                    <button type="button" class="btn btn-small <?= $slot === 0 ? '' : 'btn-grey' ?>" data-load-preset="<?= $slot ?>" title="<?= e(t('Guardada el {date}', ['date' => fdate($presets[$slot]['updated_at'])])) ?>"><?= $slot === 0 ? '⭐ ' : '📁 ' ?><?= e($presetLabel($slot)) ?></button>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if (!$presets): ?><span class="small muted"><?= e(t('(aún no tienes alineaciones guardadas)')) ?></span><?php endif; ?>
        </div>
    </div>
</div>

<form method="post" id="lineup-form" class="lineup-dnd">
    <?= csrf_field() ?>
    <script type="application/json" id="lineup-data"><?= json_encode(['players' => $data, 'slots' => $slotNames, 'presets' => $presetData ?: new stdClass()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <div class="lineup-layout">
        <div class="box">
            <div class="box-head"><?= e(t('Titulares')) ?> <span><?= e(t('Formación:')) ?> <strong id="formation-label">–</strong> · <?= e(t('Alineados:')) ?> <strong id="starter-count">0</strong>/11</span></div>
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
                <div class="bench-title"><?= e(t('Banquillo')) ?> <span class="small muted">— <?= e(t('si un titular se lesiona o no puede jugar, entra el suplente de su línea')) ?></span></div>
                <div class="bench-dnd">
                    <?php foreach (array_keys(Lineup::BENCH_LABELS) as $slot): ?><?= $slotHtml($slot, true) ?><?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="box squad-box">
            <div class="box-head"><?= e(t('Plantilla')) ?> <span class="small"><?= e(t('{n} jugadores', ['n' => count($players)])) ?></span></div>
            <div class="squad-filter">
                <button type="button" class="active" data-filter="all"><?= e(t('Todos')) ?></button>
                <button type="button" data-filter="free"><?= e(t('Sin alinear')) ?></button>
                <?php foreach (['GK', 'CD', 'WB', 'IM', 'W', 'FW'] as $r): ?><button type="button" data-filter="<?= $r ?>" title="<?= e(role_name($r)) ?>"><?= $r ?></button><?php endforeach; ?>
            </div>
            <div class="squad-list drop-out" id="squad-list"></div>
            <div class="box-body small muted">🖱 <?= e(t('Arrastra a los jugadores al campo o al banquillo. Suelta uno sobre otro para intercambiarlos y arrástralo de vuelta a la lista (o haz doble clic) para quitarlo. También puedes hacer clic en un jugador y luego en la posición. Pasa el ratón por encima para ver sus datos.')) ?></div>
        </div>
    </div>

    <div class="grid-2">
        <div class="box">
            <div class="box-head"><?= e(t('Táctica')) ?></div>
            <div class="box-body">
                <div class="field">
                    <label><?= e(t('Táctica')) ?></label>
                    <select name="tactic" id="tactic-select"><?php foreach (TACTICS as $k => $v): ?><option value="<?= e($k) ?>"<?= $lu['tactic'] === $k ? ' selected' : '' ?>><?= e(t($v)) ?></option><?php endforeach; ?></select>
                </div>
                <div class="field">
                    <label><?= e(t('Actitud')) ?></label>
                    <select name="attitude" id="attitude-select"><?php foreach (ATTITUDES as $k => $v): ?><option value="<?= e($k) ?>"<?= $lu['attitude'] === $k ? ' selected' : '' ?>><?= e(t($v)) ?></option><?php endforeach; ?></select>
                    <div class="hint"><?= e(t('PIC: juegas peor pero mejora el espíritu. MOTS: juegas mejor pero baja el espíritu.')) ?></div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label><?= e(t('Capitán')) ?></label>
                        <select name="captain" id="captain-select"><option value="">—</option>
                            <?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$lu['captain_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e(player_name($p)) ?> (<?= e(t('lid. {n}', ['n' => (int)$p['leadership']])) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label><?= e(t('Lanzador de faltas')) ?></label>
                        <select name="setpieces" id="setpieces-select"><option value="">—</option>
                            <?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$lu['setpieces_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e(player_name($p)) ?> (<?= e(t('BP {n}', ['n' => (int)floor((float)$p['set_pieces'])])) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="hint"><?= e(t('Sólo puedes elegir jugadores que estén alineados (titulares o suplentes).')) ?></div>
            </div>
        </div>
        <div class="box">
            <div class="box-head"><?= e(t('Enviar y guardar')) ?></div>
            <div class="box-body">
                <button class="btn btn-block btn-accent" type="submit" name="action" value="send">📨 <?= e($next ? t('Enviar para este partido') : t('Guardar alineación')) ?></button>
                <button class="btn btn-block" type="submit" name="action" value="default" style="margin-top:6px">⭐ <?= e(t('Guardar como predeterminada')) ?></button>
                <div class="hint"><?= e(t('La predeterminada se usa automáticamente en los partidos para los que no envíes alineación.')) ?></div>
                <hr>
                <div class="field">
                    <label><?= e(t('Guardar en una de tus alineaciones')) ?></label>
                    <div class="preset-save">
                        <select name="slot" id="preset-slot">
                            <?php for ($slot = 1; $slot <= Lineup::PRESET_SLOTS; $slot++): ?>
                                <option value="<?= $slot ?>" data-name="<?= e($presets[$slot]['name'] ?? '') ?>"><?= $slot ?>. <?= e(isset($presets[$slot]) ? $presets[$slot]['name'] : t('(vacía)')) ?></option>
                            <?php endfor; ?>
                        </select>
                        <input type="text" name="preset_name" id="preset-name" maxlength="40" placeholder="<?= e(t('Nombre, p. ej. «Contra rivales fuertes»')) ?>" value="<?= e($presets[1]['name'] ?? '') ?>">
                        <button class="btn btn-grey" type="submit" name="action" value="preset">💾 <?= e(t('Guardar')) ?></button>
                    </div>
                    <div class="hint"><?= e(t('Puedes guardar hasta {n} alineaciones para distintas situaciones y cargarlas cuando quieras.', ['n' => Lineup::PRESET_SLOTS])) ?></div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if ($presets): ?>
<div class="box">
    <div class="box-head"><?= e(t('Mis alineaciones guardadas')) ?></div>
    <table class="table compact">
        <thead><tr><th></th><th><?= e(t('Nombre')) ?></th><th><?= e(t('Formación')) ?></th><th><?= e(t('Táctica')) ?></th><th><?= e(t('Guardada')) ?></th><th></th></tr></thead>
        <?php foreach ($presets as $slot => $pr): $pos = array_keys($pr['lineup']['positions']);
            $d = count(array_filter($pos, fn($x) => in_array($x, ['RB', 'RCD', 'CD', 'LCD', 'LB'], true)));
            $m = count(array_filter($pos, fn($x) => in_array($x, ['RW', 'RIM', 'IM', 'LIM', 'LW'], true)));
            $f = count(array_filter($pos, fn($x) => in_array($x, ['RFW', 'FW', 'LFW'], true))); ?>
            <tr>
                <td><?= $slot === 0 ? '⭐' : '📁' ?></td>
                <td><strong><?= e($presetLabel($slot)) ?></strong><?= $slot === 0 ? ' <span class="tag">' . e(t('se usa si no envías alineación')) . '</span>' : '' ?></td>
                <td><?= "$d-$m-$f" ?></td>
                <td><?= e(t(TACTICS[$pr['lineup']['tactic']] ?? 'Normal')) ?></td>
                <td class="small muted"><?= e(fdate($pr['updated_at'])) ?></td>
                <td class="right nowrap">
                    <button type="button" class="btn btn-small btn-grey" data-load-preset="<?= $slot ?>"><?= e(t('Cargar')) ?></button>
                    <form method="post" style="display:inline" data-confirm-submit="<?= e(t('¿Eliminar esta alineación guardada?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete_preset"><input type="hidden" name="slot" value="<?= $slot ?>"><button class="btn btn-small btn-grey" type="submit" title="<?= e(t('Eliminar')) ?>">✕</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div id="player-tip" class="player-tip" hidden></div>
<div class="box">
    <div class="box-head"><?= e(t('Valoraciones estimadas (con la alineación enviada, sin factor campo)')) ?></div>
    <table class="table compact">
        <tbody>
        <?php foreach (MatchEngine::SECTORS as $k => $label): ?>
            <tr><td style="width:180px"><?= e(t($label)) ?></td><td><?= skill_bar($preview[$k]) ?> <?= e(rating_name($preview[$k])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small"><?= e(t('Consejos: tres centrales, mediocentros o delanteros en la misma línea rinden algo menos cada uno (se estorban). Las órdenes ofensivas suben el ataque y bajan la defensa del jugador, y al revés con las defensivas. Mientras arrastras a un jugador, las posiciones se colorean según lo bien que rendiría en cada una.')) ?></p>
