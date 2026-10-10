<?php
$team = require_team();
$pageTitle = 'Estadio';
$navActive = 'club';
$types = ['terraces' => 'Gradas de pie', 'basic' => 'Asientos básicos', 'roof' => 'Asientos cubiertos', 'vip' => 'Palcos VIP'];

if (is_post()) {
    csrf_check();
    if (post('rename')) {
        $n = trim((string)post('stadium_name'));
        if (mb_strlen($n) >= 3 && mb_strlen($n) <= 60) {
            DB::exec('UPDATE teams SET stadium_name = ? WHERE id = ?', [$n, $team['id']]);
            flash('success', 'Nombre del estadio actualizado.');
        } else {
            flash('error', 'El nombre debe tener entre 3 y 60 caracteres.');
        }
        redirect(url('stadium'));
    }
    if ($team['build_finishes_at']) {
        flash('error', 'Ya hay obras en curso.');
        redirect(url('stadium'));
    }
    $changes = [];
    foreach ($types as $k => $_) {
        $v = (int)post($k, 0);
        if ($v < 0 && abs($v) > (int)$team['seats_' . $k]) {
            flash('error', 'No puedes demoler más asientos de los que tienes.');
            redirect(url('stadium'));
        }
        $changes[$k] = max(-100000, min(100000, $v));
    }
    $cost = Economy::constructionCost($changes);
    if ($cost <= 0) {
        flash('error', 'No has indicado ningún cambio.');
    } elseif ((int)$team['cash'] < $cost) {
        flash('error', 'No tienes dinero suficiente. La obra cuesta ' . money($cost) . '.');
    } else {
        $days = Config::int('economy', 'build_days', 7);
        DB::transaction(function () use ($team, $changes, $cost, $days) {
            Economy::transaction((int)$team['id'], -$cost, 'construction', 'Obras en el estadio');
            DB::update('teams', [
                'build_terraces' => $changes['terraces'], 'build_basic' => $changes['basic'],
                'build_roof' => $changes['roof'], 'build_vip' => $changes['vip'],
                'build_finishes_at' => date('Y-m-d H:i:s', time() + $days * 86400),
            ], 'id = ?', [$team['id']]);
        });
        flash('success', 'Obras contratadas por ' . money($cost) . ". Terminarán en $days días.");
    }
    redirect(url('stadium'));
}

$capacity = 0;
$income = 0;
foreach ($types as $k => $_) {
    $capacity += (int)$team['seats_' . $k];
    $income += (int)$team['seats_' . $k] * Config::float('economy', 'price_' . $k);
}
$lastHome = DB::row("SELECT attendance FROM matches WHERE home_team_id = ? AND status = 'played' AND type = 'league' ORDER BY played_at DESC LIMIT 1", [$team['id']]);
?>
<div class="page-title"><h1><?= e($team['stadium_name']) ?></h1><span class="muted">Capacidad total: <strong><?= num($capacity) ?></strong></span></div>

<div class="grid-2">
    <div class="box">
        <div class="box-head">Localidades</div>
        <table class="table">
            <thead><tr><th>Tipo</th><th class="num">Asientos</th><th class="num">Precio</th><th class="num">Mant./sem.</th></tr></thead>
            <?php foreach ($types as $k => $label): ?>
                <tr><td><?= e($label) ?></td><td class="num"><?= num($team['seats_' . $k]) ?></td><td class="num"><?= e(money(Config::float('economy', 'price_' . $k))) ?></td><td class="num"><?= e(money((int)$team['seats_' . $k] * Config::float('economy', 'maint_' . $k))) ?></td></tr>
            <?php endforeach; ?>
            <tr><td><strong>Total</strong></td><td class="num"><strong><?= num($capacity) ?></strong></td><td></td><td class="num"><strong><?= e(money(Economy::maintenance($team))) ?></strong></td></tr>
        </table>
        <div class="box-body small">
            Ingreso máximo por partido con el estadio lleno: <strong><?= e(money($income)) ?></strong><br>
            Asistencia en el último partido de liga en casa: <strong><?= $lastHome ? num($lastHome['attendance']) : '-' ?></strong>
        </div>
    </div>
    <div class="box">
        <div class="box-head">Ampliar o reducir</div>
        <div class="box-body">
            <?php if ($team['build_finishes_at']): ?>
                <div class="alert alert-info">🏗 Obras en curso. Terminarán el <strong><?= e(fdate($team['build_finishes_at'])) ?></strong>.<br>
                    <?php foreach ($types as $k => $label): if ((int)$team['build_' . $k]): ?>
                        <?= e($label) ?>: <?= (int)$team['build_' . $k] > 0 ? '+' : '' ?><?= num($team['build_' . $k]) ?><br>
                    <?php endif; endforeach; ?>
                </div>
            <?php else: ?>
                <form method="post" id="build-form" data-confirm-submit="¿Contratar estas obras? El coste se descuenta inmediatamente.">
                    <?= csrf_field() ?>
                    <?php foreach ($types as $k => $label): ?>
                        <div class="field">
                            <label><?= e($label) ?> <span class="muted small">(<?= e(money(Config::float('economy', 'build_' . $k))) ?> por asiento)</span></label>
                            <input type="number" name="<?= $k ?>" value="0" step="100" data-price="<?= Config::float('economy', 'build_' . $k) ?>">
                        </div>
                    <?php endforeach; ?>
                    <p class="small muted">Usa números negativos para demoler (<?= e(money(Config::float('economy', 'demolish_cost'))) ?> por asiento). Toda obra tiene un coste fijo de <?= e(money(Config::int('economy', 'build_fixed_cost'))) ?> y tarda <?= Config::int('economy', 'build_days', 7) ?> días.</p>
                    <p>Coste estimado: <strong id="build-cost">0</strong> <?= e((string)Config::get('app', 'currency', '€')) ?></p>
                    <button class="btn btn-block" type="submit">Contratar obras</button>
                </form>
                <script>
                (function(){var f=document.getElementById('build-form'),o=document.getElementById('build-cost'),fixed=<?= Config::int('economy', 'build_fixed_cost', 10000) ?>,dem=<?= Config::float('economy', 'demolish_cost', 6) ?>;
                function c(){var t=0,a=false;f.querySelectorAll('input[data-price]').forEach(function(i){var v=parseInt(i.value||0,10);if(v>0){t+=v*parseFloat(i.dataset.price);a=true;}else if(v<0){t+=-v*dem;a=true;}});o.textContent=(a?Math.round(t+fixed):0).toLocaleString('es-ES');}
                f.addEventListener('input',c);c();})();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<br>
<div class="box">
    <div class="box-head">Nombre del estadio</div>
    <div class="box-body">
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="rename" value="1">
            <input type="text" name="stadium_name" value="<?= e($team['stadium_name']) ?>" maxlength="60" style="min-width:260px">
            <button class="btn btn-grey" type="submit">Cambiar nombre</button></form>
    </div>
</div>
