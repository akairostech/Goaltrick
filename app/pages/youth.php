<?php
$team = require_team();
$pageTitle = 'Cantera';
$navActive = 'club';
$weekIdx = TeamService::weekIndex();
$canPull = (int)$team['youth_pulled_week'] !== $weekIdx;

if (is_post()) {
    csrf_check();
    if (post('action') === 'level') {
        $lvl = (int)post('level');
        if ($lvl >= 0 && $lvl <= 3) {
            DB::exec('UPDATE teams SET youth_level = ? WHERE id = ?', [$lvl, $team['id']]);
            flash('success', 'Inversión en cantera actualizada.');
        }
    } elseif (post('action') === 'pull') {
        if (!$canPull) {
            flash('error', 'Ya has subido un juvenil esta semana.');
        } elseif (TeamService::squadCount((int)$team['id']) >= Config::int('game', 'max_players', 40)) {
            flash('error', 'Tu plantilla está llena.');
        } else {
            $pid = TeamService::pullYouth($team);
            DB::insert('team_events', ['team_id' => $team['id'], 'created_at' => now(), 'type' => 'youth', 'message' => '🌱 Un nuevo juvenil se une al primer equipo.']);
            flash('success', '¡Un nuevo juvenil se ha incorporado al primer equipo!');
            redirect(url('player', ['id' => $pid]));
        }
    }
    redirect(url('youth'));
}
$levels = [0 => 'Sin inversión', 1 => 'Básica', 2 => 'Buena', 3 => 'Excelente'];
$pts = (float)$team['youth_points'];
$quality = $pts < 5 ? 'Muy poco prometedor' : ($pts < 10 ? 'Poco prometedor' : ($pts < 18 ? 'Prometedor' : ($pts < 28 ? 'Muy prometedor' : 'Excepcional')));
?>
<div class="page-title"><h1>Cantera</h1></div>
<div class="grid-2">
    <div class="box">
        <div class="box-head">Inversión semanal</div>
        <div class="box-body">
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="level">
                <?php foreach ($levels as $l => $n): ?>
                    <label class="checkbox"><input type="radio" name="level" value="<?= $l ?>"<?= (int)$team['youth_level'] === $l ? ' checked' : '' ?>> <?= e($n) ?> — <?= e(money($l * Config::int('economy', 'youth_cost', 5000))) ?>/semana</label>
                <?php endforeach; ?>
                <br><button class="btn" type="submit">Guardar</button>
            </form>
            <p class="small muted" style="margin-top:10px">Cuanto más inviertas y más semanas esperes, mejores serán los juveniles que suban al primer equipo.</p>
        </div>
    </div>
    <div class="box">
        <div class="box-head">Subir un juvenil</div>
        <div class="box-body">
            <p>Valoración del mejor juvenil disponible: <strong><?= e($quality) ?></strong></p>
            <p class="small muted">Puedes subir un juvenil de 17 años por semana. Al hacerlo, la cantera vuelve a empezar desde cero.</p>
            <form method="post" data-confirm-submit="¿Subir ahora un juvenil al primer equipo?"><?= csrf_field() ?><input type="hidden" name="action" value="pull">
                <button class="btn btn-accent" type="submit" <?= $canPull ? '' : 'disabled' ?>>🌱 Subir juvenil al primer equipo</button>
            </form>
            <?php if (!$canPull): ?><p class="small muted">Ya has subido un juvenil esta semana.</p><?php endif; ?>
        </div>
    </div>
</div>
