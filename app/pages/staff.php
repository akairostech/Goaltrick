<?php
$team = require_team();
$pageTitle = t('Personal');
$navActive = 'club';
$teamId = (int)$team['id'];
$wage = Config::int('economy', 'staff_wage', 1800);
$hireCost = $wage * 2;
$week = TeamService::weekIndex();
$roleDesc = [
    'assistant'    => 'Cada ayudante acelera el entrenamiento un 2,5%.',
    'physio'       => 'Reducen el riesgo de lesiones en los partidos.',
    'doctor'       => 'Los lesionados se recuperan más rápido.',
    'spokesperson' => 'Mejoran el ánimo de la afición (máx. efecto con 3).',
    'psychologist' => 'Mejoran el espíritu y la confianza del equipo (máx. efecto con 3).',
];
$trainerNames = [1 => 'Desastroso', 2 => 'Pobre', 3 => 'Aceptable', 4 => 'Excelente', 5 => 'Destacado'];
// Candidatos estables durante la semana (misma semilla)
$candidate = fn(string $role, int $slot, ?int $level = null) => StaffService::generate($role, (int)$team['country_id'], $level, crc32("$teamId|$week|$role|$slot"));

if (is_post()) {
    csrf_check();
    $action = (string)post('action');
    if ($action === 'trainer') {
        $lvl = (int)post('level');
        $cost = Config::int('economy', 'trainer_hire_cost', 15000) * $lvl * $lvl;
        if ($lvl < 1 || $lvl > 5) {
            flash('error', t('Candidato no válido.'));
        } elseif ((int)$team['cash'] < $cost) {
            flash('error', t('No tienes suficiente dinero ({amount}).', ['amount' => money($cost)]));
        } else {
            $c = $candidate('trainer', $lvl, $lvl);
            DB::transaction(function () use ($teamId, $c, $cost, $lvl) {
                Economy::transaction($teamId, -$cost, 'staff', tx('Contratación del entrenador {name}', ['name' => StaffService::name($c)]));
                DB::exec("DELETE FROM staff WHERE team_id = ? AND role = 'trainer'", [$teamId]);
                StaffService::hire($teamId, $c);
            });
            DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'staff',
                'message' => '🧑‍💼 ' . tx('{name} es el nuevo entrenador (nivel {level}).', ['name' => StaffService::name($c), 'level' => $lvl])]);
            flash('success', t('{name} es tu nuevo entrenador.', ['name' => StaffService::name($c)]));
        }
    } elseif ($action === 'hire' && isset($roleDesc[(string)post('role')])) {
        $role = (string)post('role');
        $count = (int)DB::value('SELECT COUNT(*) FROM staff WHERE team_id = ? AND role = ?', [$teamId, $role]);
        if ($count >= 10) {
            flash('error', t('Puedes tener como máximo 10 personas en cada puesto.'));
        } elseif ((int)$team['cash'] < $hireCost) {
            flash('error', t('No tienes suficiente dinero.'));
        } else {
            $c = $candidate($role, $count);
            DB::transaction(function () use ($teamId, $c, $hireCost) {
                Economy::transaction($teamId, -$hireCost, 'staff', tx('Contratación: {name}', ['name' => StaffService::name($c)]));
                StaffService::hire($teamId, $c);
            });
            flash('success', t('{name} se incorpora al club.', ['name' => StaffService::name($c)]));
        }
    } elseif ($action === 'fire') {
        $s = DB::row("SELECT * FROM staff WHERE id = ? AND team_id = ? AND role <> 'trainer'", [(int)post('id'), $teamId]);
        if ($s) {
            DB::exec('DELETE FROM staff WHERE id = ?', [$s['id']]);
            StaffService::syncCounts($teamId);
            flash('success', t('{name} ha dejado el club.', ['name' => StaffService::name($s)]));
        }
    }
    redirect(url('staff'));
}

$team = DB::row('SELECT * FROM teams WHERE id = ?', [$teamId]);
$staff = StaffService::teamStaff($teamId);
$trainer = null;
$byRole = [];
foreach ($staff as $s) {
    if ($s['role'] === 'trainer') {
        $trainer = $s;
    } else {
        $byRole[$s['role']][] = $s;
    }
}
$countryCodes = array_column(DB::all('SELECT id, code FROM countries'), 'code', 'id');
$card = function (array $s, bool $fire = false) use ($countryCodes) {
    $code = $s['code'] ?? ($countryCodes[$s['country_id']] ?? '');
    $h = '<div class="staff-card"><img src="' . e(StaffService::avatarUrl($s)) . '" width="56" height="56" alt="">'
        . '<div class="staff-info"><div class="staff-name">' . (isset($s['id']) ? StaffService::link($s) : e(StaffService::name($s))) . '</div>'
        . '<div class="small muted">' . NationService::flagImg($code) . ' ' . e(t('{n} años', ['n' => (int)$s['age']])) . '</div>'
        . '<div class="small"><span class="tag" title="' . e(t(StaffService::PERSONALITIES[$s['personality']][1])) . '">' . e(t(StaffService::PERSONALITIES[$s['personality']][0])) . '</span></div></div>';
    if ($fire) {
        $h .= '<form method="post" data-confirm-submit="' . e(t('¿Despedir a {name}?', ['name' => StaffService::name($s)])) . '">' . csrf_field()
            . '<input type="hidden" name="action" value="fire"><input type="hidden" name="id" value="' . (int)$s['id'] . '"><button class="btn btn-small btn-grey" type="submit" title="' . e(t('Despedir')) . '">✕</button></form>';
    }
    return $h . '</div>';
};
?>
<div class="page-title"><h1><?= e(t('Personal del club')) ?></h1><span class="muted"><?= e(t('Coste semanal total:')) ?> <strong><?= e(money(Economy::weeklyStaffCost($team))) ?></strong></span></div>

<div class="box">
    <div class="box-head"><?= e(t('Entrenador')) ?></div>
    <?php if ($trainer): ?>
    <div class="box-body sponsor-main">
        <img class="avatar-big" src="<?= e(StaffService::avatarUrl($trainer)) ?>" alt="">
        <div>
            <h2 style="margin-bottom:2px"><?= StaffService::link($trainer) ?></h2>
            <div class="muted"><?= flag($trainer['code']) ?> <?= e(t($trainer['country'])) ?> · <?= e(t('{n} años', ['n' => (int)$trainer['age']])) ?></div>
            <table class="kv" style="margin-top:8px;max-width:560px">
                <tr><th><?= e(t('Nivel')) ?></th><td><?= (int)$trainer['level'] ?> · <?= e(isset($trainerNames[(int)$trainer['level']]) ? t($trainerNames[(int)$trainer['level']]) : '') ?> (<?= e(t('entrenamiento ×{factor}', ['factor' => num(Training::trainerFactor((int)$trainer['level']), 2)])) ?>)</td></tr>
                <tr><th><?= e(t('Carácter')) ?></th><td><?= e(t(StaffService::PERSONALITIES[$trainer['personality']][0])) ?> <span class="small muted">— <?= e(t(StaffService::PERSONALITIES[$trainer['personality']][1])) ?></span></td></tr>
                <tr><th><?= e(t('Salario')) ?></th><td><?= e(money(Economy::trainerWage((int)$trainer['level']))) ?> / <?= e(t('semana')) ?></td></tr>
                <tr><th><?= e(t('En el club desde')) ?></th><td><?= e(fdate($trainer['hired_at'], false)) ?></td></tr>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <div class="box-head"><?= e(t('Candidatos disponibles esta semana')) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th><?= e(t('Candidato')) ?></th><th><?= e(t('Nivel')) ?></th><th><?= e(t('Carácter')) ?></th><th class="num"><?= e(t('Salario')) ?></th><th class="num"><?= e(t('Fichaje')) ?></th><th></th></tr></thead>
        <?php for ($l = 1; $l <= 5; $l++): $c = $candidate('trainer', $l, $l); $cost = Config::int('economy', 'trainer_hire_cost', 15000) * $l * $l; ?>
            <tr>
                <td class="nowrap"><img class="avatar" src="<?= e(StaffService::avatarUrl($c)) ?>" width="30" height="30" alt=""> <?= e(StaffService::name($c)) ?> <span class="small muted"><?= NationService::flagImg($countryCodes[$c['country_id']] ?? '') ?> <?= e(t('{n} años', ['n' => (int)$c['age']])) ?></span></td>
                <td><?= $l ?> · <?= e(t($trainerNames[$l])) ?></td>
                <td><span class="tag" title="<?= e(t(StaffService::PERSONALITIES[$c['personality']][1])) ?>"><?= e(t(StaffService::PERSONALITIES[$c['personality']][0])) ?></span></td>
                <td class="num"><?= e(money(Economy::trainerWage($l))) ?></td>
                <td class="num"><?= e(money($cost)) ?></td>
                <td class="right"><form method="post" data-confirm-submit="<?= e(t('¿Contratar a {name} por {price}? Sustituirá al entrenador actual.', ['name' => StaffService::name($c), 'price' => money($cost)])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="trainer"><input type="hidden" name="level" value="<?= $l ?>"><button class="btn btn-small" type="submit"><?= e(t('Contratar')) ?></button></form></td>
            </tr>
        <?php endfor; ?>
    </table>
    </div>
    <div class="box-body small muted"><?= e(t('El carácter del entrenador influye en el entrenamiento, el espíritu, la confianza o los partidos (pasa el ratón por encima para ver el efecto). Los candidatos cambian cada semana.')) ?></div>
</div>

<?php foreach ($roleDesc as $role => $desc): $list = $byRole[$role] ?? []; $next = $candidate($role, count($list)); ?>
<div class="box">
    <div class="box-head"><?= e(t(StaffService::ROLES[$role][1])) ?> (<?= count($list) ?>) <span class="small muted" style="font-weight:normal"><?= e(t($desc)) ?></span></div>
    <div class="box-body">
        <div class="staff-grid">
            <?php foreach ($list as $s) { echo $card($s, true); } ?>
            <?php if (count($list) < 10): ?>
                <div class="staff-card candidate">
                    <img src="<?= e(StaffService::avatarUrl($next)) ?>" width="56" height="56" alt="">
                    <div class="staff-info"><div class="staff-name"><?= e(StaffService::name($next)) ?></div>
                        <div class="small muted"><?= NationService::flagImg($countryCodes[$next['country_id']] ?? '') ?> <?= e(t('{n} años', ['n' => (int)$next['age']])) ?> · <?= e(t(StaffService::PERSONALITIES[$next['personality']][0])) ?></div>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hire"><input type="hidden" name="role" value="<?= e($role) ?>"><button class="btn btn-small" type="submit"><?= e(t('Contratar ({price})', ['price' => money($hireCost)])) ?></button></form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<p class="small muted"><?= e(t('Cada miembro del personal cobra {wage} por semana. Contratar a alguien cuesta {cost}; despedir es gratis.', ['wage' => money($wage), 'cost' => money($hireCost)])) ?></p>
