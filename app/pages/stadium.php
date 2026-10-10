<?php
$team = require_team();
$pageTitle = t('Estadio');
$navActive = 'club';
$types = ['terraces' => 'Gradas de pie', 'basic' => 'Asientos básicos', 'roof' => 'Asientos cubiertos', 'vip' => 'Palcos VIP'];
$typeIcons = ['terraces' => '🧍', 'basic' => '💺', 'roof' => '⛱', 'vip' => '🥂'];

if (is_post()) {
    csrf_check();
    if (post('rename')) {
        $n = trim((string)post('stadium_name'));
        if (mb_strlen($n) >= 3 && mb_strlen($n) <= 60) {
            DB::exec('UPDATE teams SET stadium_name = ? WHERE id = ?', [$n, $team['id']]);
            flash('success', t('Nombre del estadio actualizado.'));
        } else {
            flash('error', t('El nombre debe tener entre 3 y 60 caracteres.'));
        }
        redirect(url('stadium'));
    }
    if ($team['build_finishes_at']) {
        flash('error', t('Ya hay obras en curso.'));
        redirect(url('stadium'));
    }
    $changes = [];
    foreach ($types as $k => $_) {
        $v = (int)post($k, 0);
        if ($v < 0 && abs($v) > (int)$team['seats_' . $k]) {
            flash('error', t('No puedes demoler más asientos de los que tienes.'));
            redirect(url('stadium'));
        }
        $changes[$k] = max(-100000, min(100000, $v));
    }
    $cost = Economy::constructionCost($changes);
    if ($cost <= 0) {
        flash('error', t('No has indicado ningún cambio.'));
    } elseif ((int)$team['cash'] < $cost) {
        flash('error', t('No tienes dinero suficiente. La obra cuesta {cost}.', ['cost' => money($cost)]));
    } else {
        $days = Config::int('economy', 'build_days', 7);
        DB::transaction(function () use ($team, $changes, $cost, $days) {
            Economy::transaction((int)$team['id'], -$cost, 'construction', tx('Obras en el estadio'));
            DB::update('teams', [
                'build_terraces' => $changes['terraces'], 'build_basic' => $changes['basic'],
                'build_roof' => $changes['roof'], 'build_vip' => $changes['vip'],
                'build_finishes_at' => date('Y-m-d H:i:s', time() + $days * 86400),
            ], 'id = ?', [$team['id']]);
        });
        flash('success', t('Obras contratadas por {cost}. Terminarán en {days} días.', ['cost' => money($cost), 'days' => $days]));
    }
    redirect(url('stadium'));
}

$capacity = 0;
$income = 0;
$seats = [];
foreach ($types as $k => $_) {
    $seats[$k] = (int)$team['seats_' . $k];
    $capacity += $seats[$k];
    $income += $seats[$k] * Config::float('economy', 'price_' . $k);
}
$lastHome = DB::row("SELECT attendance FROM matches WHERE home_team_id = ? AND status = 'played' AND type IN ('league', 'cup') ORDER BY played_at DESC LIMIT 1", [$team['id']]);
$occupancy = $lastHome && $capacity ? (int)$lastHome['attendance'] / $capacity : 0.55;
$building = (bool)$team['build_finishes_at'];
$model = StadiumArt::model($capacity);
$next = StadiumArt::MODELS[$model + 1] ?? null;
// Cómo quedará al terminar las obras
$after = $seats;
if ($building) {
    foreach ($types as $k => $_) {
        $after[$k] = max(0, $seats[$k] + (int)$team['build_' . $k]);
    }
}
?>
<div class="page-title"><h1>🏟 <?= e($team['stadium_name']) ?></h1><span class="muted"><?= e(t('Capacidad total:')) ?> <strong><?= num($capacity) ?></strong></span></div>

<div class="box stadium-hero">
    <div class="stadium-canvas" id="stadium-canvas">
        <?= StadiumArt::svg($seats, (int)$team['id'], $occupancy, $building, $team['stadium_name']) ?>
    </div>
    <div class="stadium-caption">
        <div>
            <div class="stadium-model"><?= e(StadiumArt::modelName($capacity)) ?></div>
            <div class="small muted"><?= e(StadiumArt::modelDescription($capacity)) ?></div>
        </div>
        <div class="stadium-tags">
            <?php foreach ($types as $k => $label): if ($seats[$k] > 0): ?>
                <span class="tag" title="<?= e(t($label)) ?>"><?= $typeIcons[$k] ?> <?= num($seats[$k]) ?></span>
            <?php endif; endforeach; ?>
            <?php if ($lastHome): ?><span class="tag" title="<?= e(t('Asistencia en el último partido oficial en casa')) ?>">👥 <?= num($lastHome['attendance']) ?> (<?= (int)round($occupancy * 100) ?>%)</span><?php endif; ?>
        </div>
    </div>
    <div class="stadium-models">
        <?php foreach (StadiumArt::MODELS as $i => [$min, $name]): ?>
            <span class="sm-step<?= $i === $model ? ' current' : ($i < $model ? ' done' : '') ?>" title="<?= e(t('Desde {n} localidades', ['n' => num($min)])) ?>"><?= e(t($name)) ?><small><?= $min ? num($min) . '+' : '0+' ?></small></span>
        <?php endforeach; ?>
    </div>
    <?php if ($next): ?>
        <div class="box-body small muted"><?= e(t('Siguiente modelo: «{name}» a partir de {n} localidades (te faltan {left}). Los techos aparecen al construir asientos cubiertos o palcos, y las gradas de pie se ven en los fondos.', ['name' => t($next[1]), 'n' => num($next[0]), 'left' => num($next[0] - $capacity)])) ?></div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Localidades')) ?></div>
        <table class="table">
            <thead><tr><th><?= e(t('Tipo')) ?></th><th class="num"><?= e(t('Asientos')) ?></th><th class="num"><?= e(t('Precio')) ?></th><th class="num"><?= e(t('Mant./sem.')) ?></th></tr></thead>
            <?php foreach ($types as $k => $label): ?>
                <tr><td><?= $typeIcons[$k] ?> <?= e(t($label)) ?></td><td class="num"><?= num($team['seats_' . $k]) ?></td><td class="num"><?= e(money(Config::float('economy', 'price_' . $k))) ?></td><td class="num"><?= e(money((int)$team['seats_' . $k] * Config::float('economy', 'maint_' . $k))) ?></td></tr>
            <?php endforeach; ?>
            <tr><td><strong><?= e(t('Total')) ?></strong></td><td class="num"><strong><?= num($capacity) ?></strong></td><td></td><td class="num"><strong><?= e(money(Economy::maintenance($team))) ?></strong></td></tr>
        </table>
        <div class="box-body small">
            <?= e(t('Ingreso máximo por partido con el estadio lleno:')) ?> <strong><?= e(money($income)) ?></strong><br>
            <?= e(t('Asistencia en el último partido oficial en casa:')) ?> <strong><?= $lastHome ? num($lastHome['attendance']) : '-' ?></strong>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Ampliar o reducir')) ?></div>
        <div class="box-body">
            <?php if ($building): ?>
                <div class="alert alert-info">🏗 <?= t('Obras en curso. Terminarán el {date}.', ['date' => '<strong>' . e(fdate($team['build_finishes_at'])) . '</strong>']) ?><br>
                    <?php foreach ($types as $k => $label): if ((int)$team['build_' . $k]): ?>
                        <?= e(t($label)) ?>: <?= (int)$team['build_' . $k] > 0 ? '+' : '' ?><?= num($team['build_' . $k]) ?><br>
                    <?php endif; endforeach; ?>
                </div>
                <div class="small muted"><?= e(t('Así quedará el estadio:')) ?> <strong><?= e(StadiumArt::modelName(array_sum($after))) ?></strong> (<?= num(array_sum($after)) ?>)</div>
                <div class="stadium-mini"><?= StadiumArt::svg($after, (int)$team['id'], 0.65, false, t('Vista previa del estadio')) ?></div>
            <?php else: ?>
                <form method="post" id="build-form" data-confirm-submit="<?= e(t('¿Contratar estas obras? El coste se descuenta inmediatamente.')) ?>">
                    <?= csrf_field() ?>
                    <?php foreach ($types as $k => $label): ?>
                        <div class="field">
                            <label><?= $typeIcons[$k] ?> <?= e(t($label)) ?> <span class="muted small">(<?= e(t('{price} por asiento', ['price' => money(Config::float('economy', 'build_' . $k))])) ?>)</span></label>
                            <input type="number" name="<?= $k ?>" value="0" step="100" data-price="<?= Config::float('economy', 'build_' . $k) ?>">
                        </div>
                    <?php endforeach; ?>
                    <p class="small muted"><?= e(t('Usa números negativos para demoler ({cost} por asiento). Toda obra tiene un coste fijo de {fixed} y tarda {days} días.', ['cost' => money(Config::float('economy', 'demolish_cost')), 'fixed' => money(Config::int('economy', 'build_fixed_cost')), 'days' => Config::int('economy', 'build_days', 7)])) ?></p>
                    <p><?= e(t('Coste estimado:')) ?> <strong id="build-cost">0</strong> <?= e((string)Config::get('app', 'currency', '€')) ?> · <?= e(t('Nueva capacidad:')) ?> <strong id="build-cap"><?= num($capacity) ?></strong></p>
                    <div class="small muted"><?= e(t('Vista previa:')) ?> <strong id="build-model"><?= e(StadiumArt::modelName($capacity)) ?></strong></div>
                    <div class="stadium-mini" id="build-preview"></div>
                    <button class="btn btn-block" type="submit"><?= e(t('Contratar obras')) ?></button>
                </form>
                <script>
                (function () {
                    var f = document.getElementById('build-form'), o = document.getElementById('build-cost'), capEl = document.getElementById('build-cap'),
                        prev = document.getElementById('build-preview'), modelEl = document.getElementById('build-model'),
                        fixed = <?= Config::int('economy', 'build_fixed_cost', 10000) ?>, dem = <?= Config::float('economy', 'demolish_cost', 6) ?>,
                        cap = <?= $capacity ?>, models = <?= json_encode(array_map(fn($m) => [$m[0], t($m[1])], StadiumArt::MODELS), JSON_UNESCAPED_UNICODE) ?>,
                        base = <?= json_encode(url('stadium_art')) ?>, timer = null;
                    function calc() {
                        var t = 0, a = false, d = 0, q = [];
                        f.querySelectorAll('input[data-price]').forEach(function (i) {
                            var v = parseInt(i.value || 0, 10) || 0;
                            if (v > 0) { t += v * parseFloat(i.dataset.price); a = true; } else if (v < 0) { t += -v * dem; a = true; }
                            d += v;
                            q.push(encodeURIComponent(i.name) + '=' + v);
                        });
                        o.textContent = (a ? Math.round(t + fixed) : 0).toLocaleString(document.documentElement.lang || 'es');
                        var nc = Math.max(0, cap + d);
                        capEl.textContent = nc.toLocaleString(document.documentElement.lang || 'es');
                        var name = models[0][1];
                        models.forEach(function (m) { if (nc >= m[0]) { name = m[1]; } });
                        modelEl.textContent = name;
                        clearTimeout(timer);
                        if (!a) { prev.innerHTML = ''; return; }
                        timer = setTimeout(function () {
                            fetch(base + '&' + q.join('&'), {credentials: 'same-origin'}).then(function (r) { return r.text(); })
                                .then(function (svg) { prev.innerHTML = svg; }).catch(function () {});
                        }, 400);
                    }
                    f.addEventListener('input', calc);
                    calc();
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<br>
<div class="box">
    <div class="box-head"><?= e(t('Nombre del estadio')) ?></div>
    <div class="box-body">
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="rename" value="1">
            <input type="text" name="stadium_name" value="<?= e($team['stadium_name']) ?>" maxlength="60" style="min-width:260px">
            <button class="btn btn-grey" type="submit"><?= e(t('Cambiar nombre')) ?></button></form>
    </div>
</div>
