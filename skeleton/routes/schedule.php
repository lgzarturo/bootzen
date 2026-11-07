<?php

/**
 * Tareas programadas. Las ejecuta php bin/bootzen schedule:run, que el servidor llama desde cron:
 *
 *   * * * * * cd /ruta/del/proyecto && php bin/bootzen schedule:run >> /dev/null 2>&1
 *
 * Revisa cuándo corre cada una con php bin/bootzen schedule:list. Las horas son de APP_TIMEZONE.
 */

declare(strict_types=1);

use BootZen\Schedule\Schedule;

return static function (Schedule $schedule): void {
    // Respaldo diario de la base SQLite en storage/backups
    // $schedule->command('db:backup')->dailyAt('03:00');

    // Con QUEUE_DRIVER=database: atiende la cola cada minuto sin dejar un proceso abierto
    // $schedule->command('queue:work --stop-when-empty --max-time=50')->everyMinute();

    // Un job o una función propia:
    // $schedule->job(new App\Jobs\LimpiarCarritos())->hourly();
    // $schedule->call(fn () => logger('sigo vivo'), 'latido')->everyFifteenMinutes();
};
