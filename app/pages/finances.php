<?php
$team = require_team();
$pageTitle = t('Finanzas');
$navActive = 'club';
$teamId = (int)$team['id'];
$season = GameState::season();
$week = GameState::week();
$prevSeason = $week > 1 ? $season : $season - 1;
$prevWeek = $week > 1 ? $week - 1 : (int)DB::value('SELECT COALESCE(MAX(week),0) FROM finances WHERE team_id = ? AND season = ?', [$teamId, $season - 1]);

$sum = function (int $s, int $w) use ($teamId) {
    $rows = DB::all('SELECT category, SUM(amount) a FROM finances WHERE team_id = ? AND season = ? AND week = ? GROUP BY category', [$teamId, $s, $w]);
    return array_column($rows, 'a', 'category');
};
$cur = $sum($season, $week);
$prev = $sum($prevSeason, $prevWeek);
$seasonRows = array_column(DB::all('SELECT category, SUM(amount) a FROM finances WHERE team_id = ? AND season = ? GROUP BY category', [$teamId, $season]), 'a', 'category');
$income = ['tickets', 'sponsors', 'ads', 'transfers_in', 'prizes'];
$expense = ['wages', 'staff', 'stadium', 'construction', 'transfers_out', 'youth', 'other'];
$ledger = DB::all('SELECT * FROM finances WHERE team_id = ? ORDER BY id DESC LIMIT 30', [$teamId]);

$lvl = (int)DB::value('SELECT level FROM league_units WHERE id = ?', [$team['league_unit_id']]);
$wages = (int)DB::value('SELECT COALESCE(SUM(wage),0) FROM players WHERE team_id = ? AND retired = 0', [$teamId]);
$estimate = [
    'Patrocinador principal' => SponsorService::weeklyFor((int)$team['id']),
    'Publicidad' => Economy::ads($team),
    'Salarios' => -$wages,
    'Personal' => -Economy::weeklyStaffCost($team),
    'Estadio' => -Economy::maintenance($team),
    'Cantera' => -(int)$team['youth_level'] * Config::int('economy', 'youth_cost', 5000),
];
$row = function (string $cat, array ...$cols) {
    $h = '<tr><td>' . e(t(Economy::CATEGORIES[$cat])) . '</td>';
    foreach ($cols as $c) {
        $v = (int)($c[$cat] ?? 0);
        $h .= '<td class="num' . ($v < 0 ? ' neg' : '') . '">' . ($v ? e(money($v)) : '<span class="muted">0</span>') . '</td>';
    }
    return $h . '</tr>';
};
$total = fn(array $c, array $cats) => array_sum(array_map(fn($k) => (int)($c[$k] ?? 0), $cats));
?>
<div class="page-title"><h1><?= e(t('Finanzas')) ?></h1><span class="muted"><?= e(t('Temporada {season}, semana {week}', ['season' => $season, 'week' => $week])) ?></span></div>

<div class="stat-tiles">
    <div class="tile"><div class="tv <?= (int)$team['cash'] < 0 ? 'neg' : '' ?>"><?= e(money($team['cash'])) ?></div><div class="tl"><?= e(t('Caja actual')) ?></div></div>
    <div class="tile"><div class="tv"><?= e(money(array_sum($estimate), true)) ?></div><div class="tl"><?= e(t('Balance semanal fijo (est.)')) ?></div></div>
    <div class="tile"><div class="tv"><?= e(money($wages)) ?></div><div class="tl"><?= e(t('Salarios / semana')) ?></div></div>
    <div class="tile"><div class="tv"><?= e(money(array_sum(array_map('intval', $seasonRows)), true)) ?></div><div class="tl"><?= e(t('Balance de la temporada')) ?></div></div>
</div>

<div class="box">
    <div class="box-head"><?= e(t('Resumen semanal')) ?></div>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th><?= e(t('Concepto')) ?></th><th class="num"><?= e(t('Esta semana')) ?></th><th class="num"><?= e(t('Semana anterior')) ?></th><th class="num"><?= e(t('Temporada')) ?></th></tr></thead>
        <tbody>
        <tr><td colspan="4"><strong><?= e(t('Ingresos')) ?></strong></td></tr>
        <?php foreach ($income as $c) { echo $row($c, $cur, $prev, $seasonRows); } ?>
        <tr><td><strong><?= e(t('Total ingresos')) ?></strong></td><td class="num pos"><?= e(money($total($cur, $income))) ?></td><td class="num pos"><?= e(money($total($prev, $income))) ?></td><td class="num pos"><?= e(money($total($seasonRows, $income))) ?></td></tr>
        <tr><td colspan="4"><strong><?= e(t('Gastos')) ?></strong></td></tr>
        <?php foreach ($expense as $c) { echo $row($c, $cur, $prev, $seasonRows); } ?>
        <tr><td><strong><?= e(t('Total gastos')) ?></strong></td><td class="num neg"><?= e(money($total($cur, $expense))) ?></td><td class="num neg"><?= e(money($total($prev, $expense))) ?></td><td class="num neg"><?= e(money($total($seasonRows, $expense))) ?></td></tr>
        <?php $b1 = $total($cur, array_merge($income, $expense)); $b2 = $total($prev, array_merge($income, $expense)); $b3 = $total($seasonRows, array_merge($income, $expense)); ?>
        <tr><td><strong><?= e(t('Balance')) ?></strong></td><td class="num <?= $b1 < 0 ? 'neg' : 'pos' ?>"><?= e(money($b1, true)) ?></td><td class="num <?= $b2 < 0 ? 'neg' : 'pos' ?>"><?= e(money($b2, true)) ?></td><td class="num <?= $b3 < 0 ? 'neg' : 'pos' ?>"><?= e(money($b3, true)) ?></td></tr>
        </tbody>
    </table>
    </div>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Previsión de la próxima actualización económica')) ?></div>
        <table class="kv">
            <?php foreach ($estimate as $k => $v): ?><tr><th><?= e(t($k)) ?></th><td class="<?= $v < 0 ? 'neg' : 'pos' ?>"><?= e(money($v, true)) ?></td></tr><?php endforeach; ?>
            <tr><th><?= e(t('Total')) ?></th><td><?= e(money(array_sum($estimate), true)) ?></td></tr>
        </table>
        <div class="box-body small muted"><?= e(t('Las entradas se cobran al jugar en casa. Los premios se reparten al final de la temporada.')) ?></div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Últimos movimientos')) ?></div>
        <table class="table compact">
            <?php foreach ($ledger as $l): ?>
                <tr><td class="small nowrap"><?= e(fdate($l['created_at'], false)) ?></td><td class="small"><?= e($l['description'] ? tdb($l['description']) : (isset(Economy::CATEGORIES[$l['category']]) ? t(Economy::CATEGORIES[$l['category']]) : $l['category'])) ?></td><td class="num <?= (int)$l['amount'] < 0 ? 'neg' : 'pos' ?>"><?= e(money($l['amount'], true)) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
