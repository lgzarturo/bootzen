# Tareas programadas

**Cuándo:** algo debe pasar solo cada cierto tiempo: respaldo diario,
recordatorios, limpiar registros viejos, atender la cola.

Se definen en `routes/schedule.php` y las ejecuta
`php bin/bootzen schedule:run`, que el servidor llama desde **una sola
línea de cron**.

## Pasos

1. Agrega las tareas en `routes/schedule.php`:

   ```php
   return static function (Schedule $schedule): void {
       $schedule->command('db:backup')->dailyAt('03:00');
       $schedule->command('queue:work --stop-when-empty --max-time=50')->everyMinute();
       $schedule->job(new EnviarRecordatorios())->weekdaysAt('09:00');
       $schedule->call(function (Connection $db): void {
           $db->statement("DELETE FROM sesiones_demo WHERE created_at < ?", [date('Y-m-d', strtotime('-30 days'))]);
       }, 'limpiar demos')->daily();
   };
   ```

   - `command('nombre --opciones')`: cualquier comando de `bin/bootzen`.
   - `job(new MiJob(...))`: lo encola (con `sync` corre en el momento).
   - `call(fn, 'nombre')`: una función; sus parámetros llegan del contenedor.

2. Frecuencias: `everyMinute()`, `everyFiveMinutes()`, `everyTenMinutes()`,
   `everyFifteenMinutes()`, `everyThirtyMinutes()`, `hourly()`,
   `hourlyAt(15)`, `daily()`, `dailyAt('03:00')`, `twiceDaily(9, 18)`,
   `weekly()`, `weeklyOn(1, '08:00')` (0 domingo), `weekdaysAt('09:00')`,
   `monthly()`, `monthlyOn(15, '12:00')` o `cron('*/10 8-18 * * 1-5')`.
   Sin frecuencia corre cada minuto. Las horas son de `APP_TIMEZONE`.

3. Revisa: `php bin/bootzen schedule:list` muestra cada tarea, cuándo corre
   y la línea de cron con la ruta completa de PHP.

4. En el servidor, agrega esa línea al cron (en un hosting, desde el panel):

   ```
   * * * * * cd /ruta/del/proyecto && /ruta/de/php bin/bootzen schedule:run >> /dev/null 2>&1
   ```

   Si el hosting solo permite cada 5 o 15 minutos, sirve igual: lo que
   tocaba en medio corre en la siguiente vuelta (una vez).

## Test

Prueba lo que hace la tarea (el comando, el job o la función) como
cualquier otro código. Para la programación en sí, `schedule:list` basta.

## Errores comunes

- En el cron, `php` a secas: cron no usa el PATH de la terminal y en
  muchos servidores no encuentra PHP. Copia la línea de `schedule:list`.
- Tareas largas cada minuto: una tarea que sigue corriendo se salta en la
  siguiente vuelta, pero las demás esperan a que termine. Usa
  `--max-time=50` en `queue:work` y manda lo pesado a la cola.
- Leer `env()` dentro de `routes/schedule.php`: usa `config()`.
- `doctor` avisa si hay tareas y `schedule:run` no corre desde hace 20
  minutos o nunca corrió.
