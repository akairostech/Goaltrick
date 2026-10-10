<?php
declare(strict_types=1);

/**
 * Cara de un jugador con el uniforme de su club (SVG generado al vuelo).
 *   face.php?a=123&k=456   cara nº 123 (1..400) con la camiseta del uniforme nº 456 (1..1000)
 * No usa la base de datos ni la sesión, así que es muy rápido y se puede cachear.
 */
define('GT_NO_SESSION', true);
require __DIR__ . '/app/bootstrap.php';

$a = max(1, min(ClubArt::FACE_COUNT, (int)($_GET['a'] ?? 1)));
$k = max(1, min(ClubArt::COUNT, (int)($_GET['k'] ?? 1)));
$etag = '"f' . $a . '-' . $k . '-' . APP_VERSION . '"';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=2592000');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
$svg = ClubArt::faceSvg($a, $k);
if ($svg === null) {
    http_response_code(404);
    exit;
}
echo $svg;
