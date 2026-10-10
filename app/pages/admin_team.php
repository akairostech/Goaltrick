<?php
/** Administración: buscar y editar cualquier equipo (dinero, personal, estadio, plantilla...) */
$admin = require_admin();
$pageTitle = 'Editar equipo';
$navActive = 'admin';
$id = (int)get('id');
$team = $id ? DB::row('SELECT t.*, u.username, c.name AS country, c.code, lu.name AS unit FROM teams t LEFT JOIN users u ON u.id = t.user_id
    JOIN countries c ON c.id = t.country_id LEFT JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.id = ?', [$id]) : null;

$intFields = [
    'fans' => [0, 10000000], 'trainer_level' => [1, 5], 'assistants' => [0, 10], 'physios' => [0, 10], 'doctors' => [0, 10],
    'spokespersons' => [0, 10], 'psychologists' => [0, 10], 'training_intensity' => [0, 100], 'stamina_share' => [5, 50],
    'youth_level' => [0, 3], 'seats_terraces' => [0, 500000], 'seats_basic' => [0, 500000], 'seats_roof' => [0, 500000], 'seats_vip' => [0, 100000],
];
$decFields = ['fan_mood' => [0, 10], 'team_spirit' => [0, 10], 'confidence' => [0, 10], 'youth_points' => [0, 40]];

if ($team && is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'save') {
        $upd = [];
        $name = trim((string)post('name'));
        if (mb_strlen($name) >= 2 && mb_strlen($name) <= 40) {
            if (DB::value('SELECT COUNT(*) FROM teams WHERE name = ? AND id <> ?', [$name, $id])) {
                flash('error', 'Ya existe otro equipo con ese nombre; no se ha cambiado el nombre.');
            } else {
                $upd['name'] = $name;
                $upd['short_name'] = mb_substr(trim((string)post('short_name')) ?: TeamService::shortName($name), 0, 12);
            }
        }
        $stadium = trim((string)post('stadium_name'));
        if ($stadium !== '') {
            $upd['stadium_name'] = mb_substr($stadium, 0, 60);
        }
        foreach ($intFields as $f => [$min, $max]) {
            $upd[$f] = (int)clamp((float)post($f, $team[$f]), $min, $max);
        }
        foreach ($decFields as $f => [$min, $max]) {
            $upd[$f] = round(clamp((float)str_replace(',', '.', (string)post($f, $team[$f])), $min, $max), 2);
        }
        $tt = (string)post('training_type');
        if (isset(TRAINING_TYPES[$tt])) {
            $upd['training_type'] = $tt;
        }
        if (post('cancel_build')) {
            $upd += ['build_terraces' => 0, 'build_basic' => 0, 'build_roof' => 0, 'build_vip' => 0, 'build_finishes_at' => null];
        }
        DB::transaction(function () use ($upd, $id, $team) {
            DB::update('teams', $upd, 'id = ?', [$id]);
            // El dinero se ajusta con un movimiento para que quede registrado en las finanzas
            $newCash = (int)post('cash', $team['cash']);
            $diff = $newCash - (int)$team['cash'];
            if ($diff !== 0) {
                Economy::transaction($id, $diff, 'other', 'Ajuste del administrador');
            }
            // Las personas del cuerpo técnico se ajustan a las nuevas cantidades
            StaffService::reconcile($id);
        });
        flash('success', 'Equipo actualizado.');
    } elseif ($a === 'add_player') {
        $role = (string)post('role');
        $pid = PlayerFactory::create([
            'role' => isset(ROLES[$role]) ? $role : 'IM', 'level' => clamp((float)post('level', 6), 1, 19),
            'age_min' => (int)clamp((float)post('age', 22), 17, 40), 'age_max' => (int)clamp((float)post('age', 22), 17, 40),
            'team_id' => $id, 'home_country' => (int)$team['country_id'], 'exact' => true,
        ]);
        flash('success', 'Jugador creado. Puedes ajustar sus habilidades ahora.');
        redirect(url('admin_player', ['id' => $pid]));
    } elseif ($a === 'release_player') {
        $pid = (int)post('player_id');
        DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE player_id = ? AND status = 'active'", [now(), $pid]);
        DB::exec('UPDATE players SET team_id = NULL, retired = 1 WHERE id = ? AND team_id = ?', [$pid, $id]);
        flash('success', 'Jugador retirado del equipo.');
    } elseif ($a === 'new_offers') {
        DB::exec("UPDATE sponsor_contracts SET status = 'ended' WHERE team_id = ? AND status = 'active'", [$id]);
        SponsorService::generateOffers($id);
        flash('success', 'Contrato anterior terminado y nuevas ofertas de patrocinio generadas.');
    } elseif ($a === 'regen_lineup') {
        Lineup::save($id, Lineup::auto($id));
        flash('success', 'Alineación automática generada.');
    } elseif ($a === 'to_bot' && $team['user_id']) {
        TeamService::release($id);
        flash('success', 'El equipo ha pasado a control de la máquina.');
    }
    redirect(url('admin_team', ['id' => $id]));
}
?>
<div class="page-title"><h1>Administración · Equipos</h1><a href="<?= e(url('admin')) ?>">« Volver a Administración</a></div>

<div class="box"><div class="box-head">Buscar equipo</div><div class="box-body">
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="admin_team">
        <input type="text" name="q" value="<?= e((string)get('q')) ?>" placeholder="Nombre del equipo, usuario o ID" style="min-width:280px">
        <label class="checkbox" style="margin:0"><input type="checkbox" name="humans" value="1"<?= get('humans') ? ' checked' : '' ?>> Sólo equipos de mánagers</label>
        <button class="btn" type="submit">Buscar</button>
    </form>
    <?php
    $q = trim((string)get('q'));
    if ($q !== '' || get('humans') || !$team):
        $where = ['t.is_national = 0'];
        $params = [];
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where[] = 't.id = ?';
                $params[] = (int)$q;
            } else {
                $where[] = '(t.name LIKE ? OR u.username LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }
        if (get('humans') || ($q === '' && !$team)) {
            $where[] = 't.user_id IS NOT NULL';
        }
        $rows = DB::all('SELECT t.id, t.name, t.cash, u.username, c.code, lu.name AS unit FROM teams t LEFT JOIN users u ON u.id = t.user_id
            JOIN countries c ON c.id = t.country_id LEFT JOIN league_units lu ON lu.id = t.league_unit_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.name LIMIT 60', $params);
    ?>
        <table class="table compact" style="margin-top:10px">
            <thead><tr><th>ID</th><th>Equipo</th><th>Mánager</th><th>Serie</th><th class="num">Caja</th><th></th></tr></thead>
            <?php foreach ($rows as $r): ?>
                <tr><td><?= (int)$r['id'] ?></td><td><?= flag($r['code']) ?> <?= e($r['name']) ?></td><td><?= e($r['username'] ?? '— máquina —') ?></td><td><?= e($r['unit']) ?></td>
                    <td class="num"><?= e(money($r['cash'])) ?></td><td><a class="btn btn-small" href="<?= e(url('admin_team', ['id' => $r['id']])) ?>">Editar</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="muted">Sin resultados.</td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
</div></div>

<?php if ($team):
    $players = DB::all("SELECT * FROM players WHERE team_id = ? AND retired = 0 ORDER BY FIELD(role,'GK','CD','WB','IM','W','FW'), tsi DESC", [$id]);
    $num = fn(string $f, $step = 1) => '<input type="number" name="' . $f . '" value="' . e($team[$f]) . '" step="' . $step . '">';
?>
<form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="box">
        <div class="box-head"><?= flag($team['code']) ?> <?= e($team['name']) ?> (#<?= $id ?>) · <?= $team['username'] ? 'Mánager: ' . e($team['username']) : 'Controlado por la máquina' ?> · Serie <?= e($team['unit'] ?? '-') ?>
            <a href="<?= e(url('team', ['id' => $id])) ?>">Ver página pública »</a></div>
        <div class="box-body">
            <h3>Club</h3>
            <div class="form-row">
                <div class="field"><label>Nombre</label><input type="text" name="name" value="<?= e($team['name']) ?>" maxlength="40"></div>
                <div class="field"><label>Nombre corto</label><input type="text" name="short_name" value="<?= e($team['short_name']) ?>" maxlength="12"></div>
                <div class="field"><label>Dinero en caja (<?= e((string)Config::get('app', 'currency', '€')) ?>)</label><input type="number" name="cash" value="<?= (int)$team['cash'] ?>" step="any"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Socios</label><?= $num('fans') ?></div>
                <div class="field"><label>Ánimo afición (0-10)</label><?= $num('fan_mood', 'any') ?></div>
                <div class="field"><label>Espíritu (0-10)</label><?= $num('team_spirit', 'any') ?></div>
                <div class="field"><label>Confianza (0-10)</label><?= $num('confidence', 'any') ?></div>
            </div>
            <h3>Personal</h3>
            <div class="form-row">
                <div class="field"><label>Entrenador (1-5)</label><?= $num('trainer_level') ?></div>
                <div class="field"><label>Ayudantes</label><?= $num('assistants') ?></div>
                <div class="field"><label>Fisios</label><?= $num('physios') ?></div>
                <div class="field"><label>Médicos</label><?= $num('doctors') ?></div>
                <div class="field"><label>Portavoces</label><?= $num('spokespersons') ?></div>
                <div class="field"><label>Psicólogos</label><?= $num('psychologists') ?></div>
            </div>
            <h3>Entrenamiento y cantera</h3>
            <div class="form-row">
                <div class="field"><label>Tipo</label><select name="training_type"><?php foreach (TRAINING_TYPES as $k => $v): ?><option value="<?= $k ?>"<?= $team['training_type'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Intensidad (%)</label><?= $num('training_intensity') ?></div>
                <div class="field"><label>Condición (%)</label><?= $num('stamina_share') ?></div>
                <div class="field"><label>Inversión cantera (0-3)</label><?= $num('youth_level') ?></div>
                <div class="field"><label>Puntos de cantera (0-40)</label><?= $num('youth_points', 'any') ?></div>
            </div>
            <h3>Estadio</h3>
            <div class="form-row">
                <div class="field" style="flex-basis:260px"><label>Nombre del estadio</label><input type="text" name="stadium_name" value="<?= e($team['stadium_name']) ?>" maxlength="60"></div>
                <div class="field"><label>Gradas de pie</label><?= $num('seats_terraces') ?></div>
                <div class="field"><label>Asientos básicos</label><?= $num('seats_basic') ?></div>
                <div class="field"><label>Cubiertos</label><?= $num('seats_roof') ?></div>
                <div class="field"><label>Palcos VIP</label><?= $num('seats_vip') ?></div>
            </div>
            <?php if ($team['build_finishes_at']): ?>
                <label class="checkbox"><input type="checkbox" name="cancel_build" value="1"> Cancelar las obras en curso (terminan el <?= e(fdate($team['build_finishes_at'])) ?>)</label>
            <?php endif; ?>
            <br><button class="btn btn-accent" type="submit">Guardar cambios del equipo</button>
            <span class="small muted">Los cambios de dinero quedan registrados en las finanzas del club como «Ajuste del administrador».</span>
        </div>
    </div>
</form>

<?php $sp = SponsorService::activeContract($id); $offersN = count(SponsorService::offers($id)); ?>
<div class="box"><div class="box-head">Patrocinio</div><div class="box-body">
    <?php if ($sp): ?>
        <img src="<?= e(SponsorService::logoUrl((int)$sp['company_id'])) ?>" width="40" height="40" class="avatar" alt=""> <strong><?= e($sp['company']) ?></strong>
        · <?= e(SponsorService::TYPES[$sp['type']][0]) ?> · <?= e(money($sp['weekly_amount'])) ?>/semana · temporadas <?= (int)$sp['season_from'] ?>–<?= (int)$sp['season_to'] ?> · cobrado <?= e(money($sp['total_paid'])) ?>
    <?php else: ?>
        <span class="muted">Sin patrocinador<?= $offersN ? " ($offersN ofertas pendientes)" : '' ?>.</span>
    <?php endif; ?>
    <form method="post" class="inline-form" style="margin-left:10px" data-confirm-submit="¿Terminar el contrato actual y generar 3 ofertas nuevas?"><?= csrf_field() ?><input type="hidden" name="action" value="new_offers"><button class="btn btn-small btn-grey" type="submit">Generar ofertas nuevas</button></form>
</div></div>

<div class="box">
    <div class="box-head">Plantilla (<?= count($players) ?> jugadores)
        <span>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="regen_lineup"><button class="btn btn-small btn-grey" type="submit">Regenerar alineación</button></form>
            <?php if ($team['user_id']): ?>
            <form method="post" class="inline-form" data-confirm-submit="¿Quitar el equipo a su mánager y dárselo a la máquina?"><?= csrf_field() ?><input type="hidden" name="action" value="to_bot"><button class="btn btn-small btn-danger" type="submit">Pasar a la máquina</button></form>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th>Jugador</th><th>Pos</th><th>Edad</th><th class="num">TSI</th><th class="num">Cnd</th><th class="num">Por</th><th class="num">Def</th><th class="num">Jug</th><th class="num">Lat</th><th class="num">Pas</th><th class="num">Ano</th><th class="num">BP</th><th class="num">Salario</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($players as $p): ?>
            <tr>
                <td class="nowrap"><?= avatar_img($p, 22) ?> <?= e(player_name($p)) ?></td>
                <td><span class="tag"><?= e($p['role']) ?></span></td>
                <td><?= (int)$p['age_years'] ?></td>
                <td class="num"><?= num($p['tsi']) ?></td>
                <?php foreach (['stamina', 'keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring', 'set_pieces'] as $sk): ?>
                    <td class="num"><?= num($p[$sk], 1) ?></td>
                <?php endforeach; ?>
                <td class="num"><?= e(money($p['wage'])) ?></td>
                <td class="nowrap">
                    <a class="btn btn-small" href="<?= e(url('admin_player', ['id' => $p['id']])) ?>">Editar</a>
                    <form method="post" class="inline-form" data-confirm-submit="¿Retirar a <?= e(player_name($p)) ?> del equipo?"><?= csrf_field() ?><input type="hidden" name="action" value="release_player"><input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-small btn-danger" type="submit">Quitar</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="box-body">
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_player">
            <strong>Añadir jugador nuevo:</strong>
            <select name="role"><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
            <label style="margin:0">Nivel</label><input type="number" name="level" value="6" min="1" max="19" step="any" style="width:70px">
            <label style="margin:0">Edad</label><input type="number" name="age" value="22" min="17" max="40" style="width:70px">
            <button class="btn" type="submit">Crear</button>
        </form>
    </div>
</div>
<?php endif; ?>
