<?php
/** Administración: editar un jugador (habilidades, edad, estado, imagen, equipo...) */
$admin = require_admin();
$pageTitle = 'Editar jugador';
$navActive = 'admin';
$id = (int)get('id');
$p = DB::row('SELECT * FROM players WHERE id = ?', [$id]);
if (!$p) {
    flash('error', 'Jugador no encontrado.');
    redirect(url('admin_team'));
}
$skillFields = array_merge(['stamina' => 'Condición'], SKILLS);

if (is_post()) {
    csrf_check();
    if (post('action') === 'retire') {
        DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE player_id = ? AND status = 'active'", [now(), $id]);
        DB::exec('UPDATE players SET team_id = NULL, retired = 1 WHERE id = ?', [$id]);
        flash('success', 'Jugador retirado.');
        redirect($p['team_id'] ? url('admin_team', ['id' => $p['team_id']]) : url('admin_team'));
    }
    $dec = fn(string $f, float $min, float $max) => round(clamp((float)str_replace(',', '.', (string)post($f, $p[$f])), $min, $max), 2);
    $upd = [
        'first_name'   => mb_substr(trim((string)post('first_name')) ?: $p['first_name'], 0, 40),
        'last_name'    => mb_substr(trim((string)post('last_name')) ?: $p['last_name'], 0, 40),
        'age_years'    => (int)clamp((float)post('age_years', $p['age_years']), 15, 45),
        'age_days'     => (int)clamp((float)post('age_days', $p['age_days']), 0, 111),
        'form'         => $dec('form', 1, 8),
        'experience'   => $dec('experience', 0, 20),
        'leadership'   => (int)clamp((float)post('leadership', $p['leadership']), 0, 20),
        'loyalty'      => $dec('loyalty', 0, 20),
        'injury_weeks' => round(clamp((float)post('injury_weeks', $p['injury_weeks']), 0, 52), 1),
        'suspended'    => (int)clamp((float)post('suspended', $p['suspended']), 0, 10),
        'yellow_cards' => (int)clamp((float)post('yellow_cards', $p['yellow_cards']), 0, 5),
        'avatar'       => (int)clamp((float)post('avatar', $p['avatar']), 1, AVATAR_COUNT),
    ];
    foreach (array_keys($skillFields) as $sk) {
        $upd[$sk] = $dec($sk, 0, 20);
    }
    $role = (string)post('role');
    if (isset(ROLES[$role])) {
        $upd['role'] = $role;
    }
    $country = (int)post('country_id');
    if ($country && DB::value('SELECT COUNT(*) FROM countries WHERE id = ?', [$country])) {
        $upd['country_id'] = $country;
    }
    $spec = (string)post('specialty');
    $upd['specialty'] = isset(SPECIALTIES[$spec]) ? $spec : null;

    $newTeam = (int)post('team_id');
    if ($newTeam !== (int)$p['team_id']) {
        if ($newTeam === 0) {
            $upd['team_id'] = null;
        } elseif (DB::value('SELECT COUNT(*) FROM teams WHERE id = ? AND is_national = 0', [$newTeam])) {
            $upd['team_id'] = $newTeam;
            $upd['retired'] = 0;
            $upd['joined_at'] = now();
            DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE player_id = ? AND status = 'active'", [now(), $id]);
        } else {
            flash('error', 'El equipo de destino no existe.');
        }
    }
    DB::update('players', $upd, 'id = ?', [$id]);
    PlayerFactory::refresh([$id]);
    DB::insert('player_events', ['player_id' => $id, 'created_at' => now(), 'message' => 'Datos modificados por la administración.']);
    flash('success', 'Jugador actualizado (TSI y salario recalculados).');
    redirect(url('admin_player', ['id' => $id]));
}
$teamName = $p['team_id'] ? DB::value('SELECT name FROM teams WHERE id = ?', [$p['team_id']]) : null;
$countries = DB::all('SELECT id, name, code FROM countries ORDER BY name');
?>
<div class="page-title">
    <div class="player-head"><img class="avatar-big" src="<?= e(avatar_url($p)) ?>" alt=""><div><h1><?= e(player_name($p)) ?></h1>
        <div class="muted">TSI <?= num($p['tsi']) ?> · Salario <?= e(money($p['wage'])) ?> · <?= $teamName ? e($teamName) : 'Sin equipo' ?><?= (int)$p['retired'] ? ' (retirado)' : '' ?></div></div></div>
    <span><?php if ($p['team_id']): ?><a href="<?= e(url('admin_team', ['id' => $p['team_id']])) ?>">« Volver al equipo</a> · <?php endif; ?><a href="<?= e(url('player', ['id' => $id])) ?>">Ficha pública »</a></span>
</div>

<form method="post">
    <?= csrf_field() ?>
    <div class="grid-2">
        <div class="box"><div class="box-head">Datos personales</div><div class="box-body">
            <div class="form-row">
                <div class="field"><label>Nombre</label><input type="text" name="first_name" value="<?= e($p['first_name']) ?>" maxlength="40"></div>
                <div class="field"><label>Apellido</label><input type="text" name="last_name" value="<?= e($p['last_name']) ?>" maxlength="40"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Edad (años)</label><input type="number" name="age_years" value="<?= (int)$p['age_years'] ?>" min="15" max="45"></div>
                <div class="field"><label>Días (0-111)</label><input type="number" name="age_days" value="<?= (int)$p['age_days'] ?>" min="0" max="111"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Posición natural</label><select name="role"><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"<?= $p['role'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Nacionalidad</label><select name="country_id"><?php foreach ($countries as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$p['country_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e(NationService::flag($c['code']) . ' ' . $c['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Especialidad</label><select name="specialty"><option value="">Ninguna</option><?php foreach (SPECIALTIES as $k => [$n, $i]): ?><option value="<?= $k ?>"<?= $p['specialty'] === $k ? ' selected' : '' ?>><?= $i ?> <?= e($n) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Equipo (ID, 0 = sin equipo)</label><input type="number" name="team_id" value="<?= (int)$p['team_id'] ?>" min="0"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Lesión (semanas)</label><input type="number" name="injury_weeks" value="<?= e($p['injury_weeks']) ?>" min="0" max="52" step="any"></div>
                <div class="field"><label>Partidos de sanción</label><input type="number" name="suspended" value="<?= (int)$p['suspended'] ?>" min="0" max="10"></div>
                <div class="field"><label>Amarillas</label><input type="number" name="yellow_cards" value="<?= (int)$p['yellow_cards'] ?>" min="0" max="5"></div>
            </div>
        </div></div>
        <div class="box"><div class="box-head">Habilidades (0 = inexistente … 20 = divino, admite decimales)</div><div class="box-body">
            <?php foreach ($skillFields as $k => $label): ?>
                <div class="form-row" style="align-items:center">
                    <label style="flex:0 0 110px;margin:0"><?= e($label) ?></label>
                    <input type="number" name="<?= $k ?>" value="<?= e($p[$k]) ?>" min="0" max="20" step="any" style="flex:0 0 90px">
                    <span class="small muted"><?= e(skill_label($p[$k])) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="form-row" style="align-items:center"><label style="flex:0 0 110px;margin:0">Forma (1-8)</label><input type="number" name="form" value="<?= e($p['form']) ?>" min="1" max="8" step="any" style="flex:0 0 90px"></div>
            <div class="form-row" style="align-items:center"><label style="flex:0 0 110px;margin:0">Experiencia</label><input type="number" name="experience" value="<?= e($p['experience']) ?>" min="0" max="20" step="any" style="flex:0 0 90px"></div>
            <div class="form-row" style="align-items:center"><label style="flex:0 0 110px;margin:0">Liderazgo</label><input type="number" name="leadership" value="<?= (int)$p['leadership'] ?>" min="0" max="20" style="flex:0 0 90px"></div>
            <div class="form-row" style="align-items:center"><label style="flex:0 0 110px;margin:0">Fidelidad</label><input type="number" name="loyalty" value="<?= e($p['loyalty']) ?>" min="0" max="20" step="any" style="flex:0 0 90px"></div>
        </div></div>
    </div>
    <div class="box"><div class="box-head">Imagen de perfil</div><div class="box-body">
        <div class="avatar-picker">
            <?php for ($i = 1; $i <= AVATAR_COUNT; $i++): ?>
                <label><input type="radio" name="avatar" value="<?= $i ?>"<?= (int)$p['avatar'] === $i ? ' checked' : '' ?>><img src="<?= e(asset(sprintf('img/avatars/%03d.svg', $i))) ?>" width="56" height="56" alt="<?= $i ?>" loading="lazy"></label>
            <?php endfor; ?>
        </div>
    </div></div>
    <button class="btn btn-accent" type="submit">Guardar jugador</button>
</form>
<br>
<form method="post" data-confirm-submit="¿Retirar a este jugador del fútbol?"><?= csrf_field() ?><input type="hidden" name="action" value="retire"><button class="btn btn-danger" type="submit">Retirar jugador</button></form>
