<?php
declare(strict_types=1);

/**
 * Tareas programadas de GoalTrick (para el Programador de tareas de Windows o cron).
 *
 * Uso:
 *   php cron/run.php                 Ejecuta todo lo pendiente (recomendado cada 5 minutos)
 *   php cron/run.php --force-next 5  Ejecuta los 5 siguientes eventos sin esperar (pruebas)
 *   php cron/run.php --status        Muestra los próximos eventos
 *   php cron/run.php --install usuario email contraseña "Nombre del equipo"
 *   php cron/run.php --upgrade       Actualiza la base de datos tras instalar una versión nueva
 *
 * Windows (Programador de tareas): programa
 *   C:\xampp\php\php.exe "C:\xampp\htdocs\goaltrick\cron\run.php"
 * Linux (crontab):
 *   *\/5 * * * * php /var/www/goaltrick/cron/run.php > /dev/null 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sólo desde la línea de comandos.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$cmd = $args[0] ?? '';

try {
    if ($cmd === '--install') {
        [$u, $e, $p, $t] = array_slice($args, 1, 4) + [null, null, null, null];
        if (!$u || !$e || !$p || !$t) {
            exit("Uso: php cron/run.php --install usuario email contraseña \"Nombre del equipo\"\n");
        }
        Installer::install($u, $e, $p, $t, fn($m) => print("$m\n"));
        exit(0);
    }
    if ($cmd === '--upgrade') {
        Installer::upgrade(fn($m) => print("$m\n"));
        exit(0);
    }
    if (Installer::needsUpgrade()) {
        exit("La base de datos necesita actualizarse. Ejecuta: php cron/run.php --upgrade\n");
    }
    if ($cmd === '--force-next') {
        $n = max(1, (int)($args[1] ?? 1));
        for ($i = 0; $i < $n; $i++) {
            echo '[' . date('H:i:s') . '] ' . Scheduler::forceNext() . "\n";
        }
        exit(0);
    }
    if ($cmd === '--status') {
        echo 'Temporada ' . GameState::season() . ', semana ' . GameState::week() . "\n";
        foreach (DB::all("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at LIMIT 10") as $ev) {
            echo $ev['run_at'] . '  ' . Scheduler::eventName($ev['type']) . ($ev['round'] ? ' ' . $ev['round'] : '') . "\n";
        }
        exit(0);
    }
    $log = Scheduler::runDue();
    foreach ($log as $l) {
        echo '[' . date('Y-m-d H:i:s') . "] $l\n";
    }
    if (!$log) {
        echo '[' . date('Y-m-d H:i:s') . "] Nada pendiente.\n";
    }
} catch (Throwable $ex) {
    fwrite(STDERR, 'ERROR: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}
