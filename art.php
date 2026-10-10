<?php
declare(strict_types=1);

/**
 * Imágenes generadas: uniformes y escudos (SVG).
 *   art.php?k=kit&n=123     uniforme número 123 (1..1000)
 *   art.php?k=crest&n=45    escudo número 45 (1..1000)
 * No usa la base de datos ni la sesión, así que es muy rápido y se puede cachear.
 */
define('GT_NO_SESSION', true);
require __DIR__ . '/app/bootstrap.php';

$k = (string)($_GET['k'] ?? '');
$n = max(1, min(ClubArt::COUNT, (int)($_GET['n'] ?? 1)));
if (!in_array($k, ['kit', 'crest'], true)) {
    http_response_code(404);
    exit;
}
$etag = '"' . $k . $n . '-' . APP_VERSION . '"';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=2592000');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $k === 'kit' ? ClubArt::kitSvg($n) : ClubArt::crestSvg($n);
