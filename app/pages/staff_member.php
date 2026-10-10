<?php
require_login();
$id = (int)get('id');
$s = DB::row('SELECT s.*, c.name AS country, c.code, t.name AS team_name, t.trainer_level FROM staff s JOIN countries c ON c.id = s.country_id
    JOIN teams t ON t.id = s.team_id WHERE s.id = ?', [$id]);
if (!$s) {
    flash('error', 'Esa persona ya no trabaja en ningún club.');
    redirect(url('staff'));
}
$pageTitle = StaffService::name($s);
$navActive = 'club';
[$roleName] = StaffService::ROLES[$s['role']];
[$pName, $pDesc] = StaffService::PERSONALITIES[$s['personality']];
$isTrainer = $s['role'] === 'trainer';
$trainerNames = [1 => 'Desastroso', 2 => 'Pobre', 3 => 'Aceptable', 4 => 'Excelente', 5 => 'Destacado'];
$wage = $isTrainer ? Economy::trainerWage((int)$s['level']) : Config::int('economy', 'staff_wage', 1800);
$colleagues = DB::all('SELECT s.*, c.code FROM staff s JOIN countries c ON c.id = s.country_id WHERE s.team_id = ? AND s.id <> ? ORDER BY FIELD(s.role, \'trainer\', \'assistant\', \'physio\', \'doctor\', \'spokesperson\', \'psychologist\') LIMIT 12', [$s['team_id'], $id]);
?>
<div class="page-title">
    <div class="player-head"><img class="avatar-big" src="<?= e(StaffService::avatarUrl($s)) ?>" alt=""><div><h1><?= e(StaffService::name($s)) ?></h1>
        <div class="muted"><?= e($roleName) ?> · <?= team_link($s['team_id'], $s['team_name']) ?></div></div></div>
</div>
<div class="grid-2">
    <div class="box"><div class="box-head">Perfil</div>
        <table class="kv">
            <tr><th>Puesto</th><td><?= e($roleName) ?></td></tr>
            <tr><th>Edad</th><td><?= (int)$s['age'] ?> años</td></tr>
            <tr><th>Nacionalidad</th><td><?= nation_link($s['country_id'], $s['country'], $s['code']) ?></td></tr>
            <tr><th>Carácter</th><td><?= e($pName) ?></td></tr>
            <?php if ($isTrainer): ?>
                <tr><th>Nivel</th><td><?= skill_bar((int)$s['level'], 5) ?> <?= (int)$s['level'] ?> · <?= e($trainerNames[(int)$s['level']] ?? '') ?></td></tr>
                <tr><th>Efecto en el entrenamiento</th><td>×<?= num(Training::trainerFactor((int)$s['level']), 2) ?></td></tr>
            <?php endif; ?>
            <tr><th>Salario</th><td><?= e(money($wage)) ?> / semana</td></tr>
            <tr><th>En el club desde</th><td><?= e(fdate($s['hired_at'], false)) ?></td></tr>
        </table>
    </div>
    <div class="box"><div class="box-head">Carácter: <?= e($pName) ?></div><div class="box-body">
        <p><?= $isTrainer ? e($pDesc) : 'Su carácter marca cómo se relaciona con los jugadores y el resto del cuerpo técnico. Sólo el carácter del entrenador tiene efectos directos en el juego.' ?></p>
        <?php if ($colleagues): ?>
            <h3>Compañeros del cuerpo técnico</h3>
            <table class="table compact">
                <?php foreach ($colleagues as $c): ?>
                    <tr><td class="nowrap"><img class="avatar" src="<?= e(StaffService::avatarUrl($c)) ?>" width="24" height="24" alt=""> <?= StaffService::link($c) ?></td><td class="small"><?= e(StaffService::ROLES[$c['role']][0]) ?></td><td class="small muted"><?= e(StaffService::PERSONALITIES[$c['personality']][0]) ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div></div>
</div>
