<?php
require_login();
$pageTitle = 'Empresas';
$navActive = 'world';
$sector = (string)get('sector');
$q = trim((string)get('q'));
$sectors = array_column(DB::all('SELECT sector, COUNT(*) n FROM companies GROUP BY sector ORDER BY sector'), 'n', 'sector');
$where = ['1=1'];
$params = [];
if (isset($sectors[$sector])) {
    $where[] = 'c.sector = ?';
    $params[] = $sector;
}
if ($q !== '') {
    $where[] = 'c.name LIKE ?';
    $params[] = '%' . $q . '%';
}
$season = GameState::season();
$rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM sponsor_contracts sc WHERE sc.company_id = c.id AND sc.status = \'active\' AND sc.season_from <= ? AND sc.season_to >= ?) AS clubs
    FROM companies c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.prestige DESC, c.name', array_merge([$season, $season], $params));
?>
<div class="page-title"><h1>Empresas patrocinadoras</h1><span class="muted"><?= num(array_sum($sectors)) ?> empresas de <?= count($sectors) ?> rubros</span></div>
<div class="box"><div class="box-body">
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="companies">
        <select name="sector"><option value="">Todos los rubros</option><?php foreach ($sectors as $s => $n): ?><option value="<?= e($s) ?>"<?= $sector === $s ? ' selected' : '' ?>><?= e($s) ?> (<?= (int)$n ?>)</option><?php endforeach; ?></select>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nombre de la empresa">
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <p class="small muted" style="margin-top:8px">Las estrellas indican el prestigio: las empresas más importantes sólo patrocinan a clubes de las divisiones altas.</p>
</div></div>
<div class="company-grid">
    <?php foreach ($rows as $c): ?>
        <div class="company" title="<?= e($c['slogan']) ?>">
            <img src="<?= e(SponsorService::logoUrl((int)$c['id'])) ?>" alt="" width="84" height="84" loading="lazy">
            <div class="company-name"><?= e($c['name']) ?></div>
            <div class="small muted"><?= e($c['sector']) ?></div>
            <div class="small"><span class="stars"><?= str_repeat('★', (int)$c['prestige']) ?></span><?= (int)$c['clubs'] ? ' · ' . (int)$c['clubs'] . ' club(es)' : '' ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="muted">No hay empresas con esos filtros.</p><?php endif; ?>
</div>
