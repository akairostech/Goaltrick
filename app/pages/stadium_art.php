<?php
// Dibujo del estadio en SVG (vista previa de obras desde la página «Estadio»)
$team = require_team();
$seats = [];
foreach (['terraces', 'basic', 'roof', 'vip'] as $k) {
    $seats[$k] = max(0, (int)$team['seats_' . $k] + max(-1000000, min(1000000, (int)get($k, 0))));
}
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');
echo StadiumArt::svg($seats, (int)$team['id'], 0.65, false, t('Vista previa del estadio'));
exit;
