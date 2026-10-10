<?php
/**
 * Ejecuta las tareas pendientes por URL (para servicios de cron externos):
 *   index.php?page=cron&key=CLAVE   (la clave está en config.ini -> [security] cron_key)
 */
$key = (string)get('key');
$expected = (string)Config::get('security', 'cron_key', '');
if ($expected === '' || $expected === 'cambia-esta-clave-secreta' || !hash_equals($expected, $key)) {
    http_response_code(403);
    exit(t('Clave incorrecta (o no configurada en config.ini).'));
}
header('Content-Type: text/plain; charset=utf-8');
$log = Scheduler::runDue();
echo $log ? implode("\n", array_map('tdb', $log)) : t('Nada pendiente.');
exit;
