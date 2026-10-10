<?php
$team = require_team();
$pageTitle = t('Entrenamiento');
$navActive = 'club';

if (is_post()) {
    csrf_check();
    $type = (string)post('training_type');
    $int = (int)post('intensity');
    $sta = (int)post('stamina_share');
    if (!isset(TRAINING_TYPES[$type])) {
        flash('error', t('Tipo de entrenamiento no válido.'));
    } else {
        DB::update('teams', [
            'training_type' => $type,
            'training_intensity' => max(0, min(100, $int)),
            'stamina_share' => max(5, min(50, $sta)),
        ], 'id = ?', [$team['id']]);
        flash('success', t('Entrenamiento actualizado.'));
    }
    redirect(url('training'));
}

$type = $team['training_type'];
$eligible = Training::ELIGIBLE[$type] ?? [];
$since = GameState::get('last_training_at') ?? '2000-01-01';
$rows = DB::all(
    'SELECT p.id, p.first_name, p.last_name, p.age_years, p.' . $type . ' AS sk, mp.position, mp.minutes
     FROM match_players mp JOIN matches m ON m.id = mp.match_id JOIN players p ON p.id = mp.player_id
     WHERE mp.team_id = ? AND m.played_at > ? ORDER BY p.last_name',
    [$team['id'], $since]
);
$will = [];
foreach ($rows as $r) {
    $ptype = Lineup::POSITIONS[$r['position']][0] ?? '';
    $f = $eligible[$ptype] ?? 0;
    $pid = (int)$r['id'];
    $will[$pid] = $will[$pid] ?? $r + ['factor' => 0];
    $will[$pid]['factor'] = min(1, $will[$pid]['factor'] + $f * (int)$r['minutes'] / 90);
}
$next = Scheduler::nextEvent('training');
$desc = [
    'keeper'     => 'Mejora la portería de los porteros.',
    'defending'  => 'Mejora la defensa de centrales y laterales.',
    'playmaking' => 'Mejora las jugadas (creación de juego) de los mediocampistas (los extremos entrenan la mitad).',
    'winger'     => 'Mejora el juego por banda de los extremos (los laterales entrenan la mitad).',
    'passing'    => 'Mejora los pases de mediocampistas, extremos y delanteros.',
    'scoring'    => 'Mejora la anotación de los delanteros.',
    'set_pieces' => 'Mejora el balón parado de todos los que jueguen (el lanzador recibe un extra).',
];
$posNames = ['GK' => 'Porteros', 'CD' => 'Centrales', 'WB' => 'Laterales', 'IM' => 'Medios##posición', 'W' => 'Extremos', 'FW' => 'Delanteros'];
?>
<div class="page-title"><h1><?= e(t('Entrenamiento')) ?></h1>
    <?php if ($next): ?><span class="muted"><?= e(t('Próxima actualización')) ?>: <strong><?= e(fdate_long($next['run_at'])) ?></strong></span><?php endif; ?>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Configuración')) ?></div>
        <div class="box-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="training_type"><?= e(t('Tipo de entrenamiento')) ?></label>
                    <select name="training_type" id="training_type">
                        <?php foreach (TRAINING_TYPES as $k => $v): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e(skill_title($k)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="intensity"><?= e(t('Intensidad (%)')) ?></label>
                    <input type="number" id="intensity" name="intensity" min="0" max="100" value="<?= (int)$team['training_intensity'] ?>">
                    <div class="hint"><?= e(t('Más intensidad = más progreso, pero el espíritu del equipo sufre.')) ?></div>
                </div>
                <div class="field">
                    <label for="stamina_share"><?= e(t('Parte dedicada a condición física (%)')) ?></label>
                    <input type="number" id="stamina_share" name="stamina_share" min="5" max="50" value="<?= (int)$team['stamina_share'] ?>">
                    <div class="hint"><?= e(t('La condición evita que tus jugadores rindan menos al final del partido. Lo que dediques a condición se resta del entrenamiento principal.')) ?></div>
                </div>
                <button class="btn btn-block" type="submit"><?= e(t('Guardar')) ?></button>
            </form>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('¿Quién entrena?')) ?></div>
        <div class="box-body">
            <p><strong><?= e(skill_title($type)) ?>:</strong> <?= e(t($desc[$type])) ?></p>
            <table class="table compact">
                <thead><tr><th><?= e(t('Posición en el partido')) ?></th><th class="num"><?= e(t('Entrenamiento')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($posNames as $k => $n): ?>
                    <tr><td><?= e(t($n)) ?></td><td class="num"><?= isset($eligible[$k]) ? (int)($eligible[$k] * 100) . '%' : '<span class="muted">—</span>' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted small"><?= e(t('Sólo entrenan los jugadores que jueguen partidos en esas posiciones durante la semana (liga o amistosos; con 90 minutos basta). Los jóvenes progresan mucho más rápido que los veteranos, y cuesta más subir en niveles altos.')) ?></p>
            <table class="kv">
                <tr><th><?= e(t('Entrenador')) ?></th><td><?= e(t('Nivel {n}/5', ['n' => (int)$team['trainer_level']])) ?> (×<?= num(Training::trainerFactor((int)$team['trainer_level']), 2) ?>)</td></tr>
                <tr><th><?= e(t('Asistentes')) ?></th><td><?= (int)$team['assistants'] ?> (+<?= (int)$team['assistants'] * 2.5 ?>%)</td></tr>
            </table>
        </div>
    </div>
</div>
<br>
<div class="box">
    <div class="box-head"><?= e(t('Jugadores que entrenarán en la próxima actualización')) ?></div>
    <?php if ($will): ?>
    <table class="table compact">
        <thead><tr><th><?= e(t('Jugador')) ?></th><th><?= e(t('Edad')) ?></th><th><?= e(t('Posición jugada')) ?></th><th><?= e(t('Nivel actual')) ?></th><th class="num"><?= e(t('Entrenamiento')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($will as $w): ?>
            <tr>
                <td><?= player_link($w) ?></td>
                <td><?= (int)$w['age_years'] ?></td>
                <td><?= e(isset(Lineup::POSITIONS[$w['position']][2]) ? t(Lineup::POSITIONS[$w['position']][2]) : $w['position']) ?></td>
                <td><?= e(skill_label($w['sk'])) ?></td>
                <td class="num"><?= $w['factor'] > 0 ? '<span class="pos">' . (int)round($w['factor'] * 100) . '%</span>' : '<span class="muted">0%</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="box-body muted"><?= e(t('Ningún jugador ha jugado todavía esta semana.')) ?></div>
    <?php endif; ?>
</div>
