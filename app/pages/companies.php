<?php
require_login();
$pageTitle = t('Empresas');
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
<div class="page-title"><h1><?= e(t('Empresas patrocinadoras')) ?></h1><span class="muted"><?= e(t('{n} empresas de {sectors} rubros', ['n' => num(array_sum($sectors)), 'sectors' => count($sectors)])) ?></span></div>
<div class="box"><div class="box-body">
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="companies">
        <select name="sector"><option value=""><?= e(t('Todos los rubros')) ?></option><?php foreach ($sectors as $s => $n): ?><option value="<?= e($s) ?>"<?= $sector === $s ? ' selected' : '' ?>><?= e(t($s)) ?> (<?= (int)$n ?>)</option><?php endforeach; ?></select>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Nombre de la empresa')) ?>">
        <button class="btn" type="submit"><?= e(t('Filtrar')) ?></button>
    </form>
    <p class="small muted" style="margin-top:8px"><?= e(t('Las estrellas indican el prestigio: las empresas más importantes sólo patrocinan a clubes de las divisiones altas.')) ?></p>
</div></div>
<div class="company-grid">
    <?php foreach ($rows as $c): ?>
        <div class="company" title="<?= e(t($c['slogan'])) ?>">
            <img src="<?= e(SponsorService::logoUrl((int)$c['id'])) ?>" alt="" width="84" height="84" loading="lazy">
            <div class="company-name"><?= e($c['name']) ?></div>
            <div class="small muted"><?= e(t($c['sector'])) ?></div>
            <div class="small"><span class="stars"><?= str_repeat('★', (int)$c['prestige']) ?></span><?= (int)$c['clubs'] ? ' · ' . e(t('{n} club(es)', ['n' => (int)$c['clubs']])) : '' ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="muted"><?= e(t('No hay empresas con esos filtros.')) ?></p><?php endif; ?>
</div>
