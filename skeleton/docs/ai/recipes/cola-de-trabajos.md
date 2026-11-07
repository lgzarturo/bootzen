# Cola de trabajos

**Cuándo:** algo tarda o puede fallar y el usuario no tiene por qué esperar:
enviar correos, generar un PDF o un reporte, llamar a una API externa,
procesar una imagen subida.

Drivers (`QUEUE_DRIVER` en `.env`, `config/queue.php`):

- `sync` (por defecto y en los tests): el job se ejecuta en el momento, en
  la misma petición. Sin tabla ni worker.
- `database`: el job se guarda en la tabla `jobs` y lo ejecuta
  `php bin/bootzen queue:work`. Para activarlo:

  ```bash
  php bin/bootzen queue:table   # migración de jobs y failed_jobs (SQLite o MySQL)
  php bin/bootzen migrate
  # en .env: QUEUE_DRIVER=database
  ```

No instales librerías de colas ni Redis: esto cubre un MVP.

## Pasos

1. `php bin/bootzen make:job GenerarReporte` crea `app/Jobs/GenerarReporte.php`.
2. Completa el constructor y `handle()`:

   ```php
   final class GenerarReporte extends Job
   {
       public int $tries = 3;     // intentos antes de darlo por fallido
       public int $backoff = 60;  // segundos entre intentos

       public function __construct(public readonly int $userId, public readonly string $mes)
       {
       }

       public function handle(Mailer $mailer): void   // dependencias del contenedor
       {
           $usuario = User::findOrFail($this->userId);
           // ... arma el reporte
           $mailer->send((new Message($usuario->email, "Reporte {$this->mes}", 'Va adjunto.'))->attachData($csv, 'reporte.csv', 'text/csv'));
       }
   }
   ```

3. Encólalo desde el controlador y responde de inmediato:

   ```php
   dispatch(new GenerarReporte((int) auth()->id(), (string) $request->input('mes')));
   dispatch(new RecordatorioPago($factura->id), 3600); // no antes de una hora

   return back()->with('status', 'Te enviamos el reporte por correo en unos minutos.');
   ```

4. Para correos basta con `mailer()->queue($message)` (sin job propio).

## Quién ejecuta la cola

- **Con cron** (hosting compartido o VPS): en `routes/schedule.php`
  descomenta `$schedule->command('queue:work --stop-when-empty --max-time=50')->everyMinute();`.
  El cron de `schedule:run` la atiende cada minuto (receta `tareas-programadas.md`).
- **Proceso permanente** (VPS con systemd o supervisor):
  `php bin/bootzen queue:work`.
- **Docker**: el servicio `scheduler` de `docker-compose.yml` ya corre
  `schedule:run` cada minuto; programa ahí `queue:work` como arriba.

## Si falla

Si `handle()` lanza una excepción, el job se reintenta `$tries` veces
esperando `$backoff` segundos. Después pasa a `failed_jobs` y se llama a
`failed(Throwable $e)` si la defines.

```bash
php bin/bootzen queue:failed     # lista los fallidos con su error
php bin/bootzen queue:retry 5    # vuelve a encolar uno (o: queue:retry all)
php bin/bootzen queue:flush      # borra los fallidos
```

`doctor` avisa si hay fallidos.

## Test

Con `QUEUE_DRIVER=sync` (ya está en `phpunit.xml`) el job corre dentro de la
petición del test, así que se prueba su efecto:

```php
it('envía el reporte por correo', function (): void {
    $client = client();
    $usuario = User::create([...]);

    $client->actingAs($usuario)->post('/reportes', ['mes' => '2026-03'])->assertRedirect();

    $mailer = $client->app->make(Mailer::class);
    $enviados = $mailer instanceof ArrayMailer ? $mailer->sent() : [];

    expect($enviados)->toHaveCount(1)
        ->and($enviados[0]->subject)->toBe('Reporte 2026-03');
});
```

## Errores comunes

- Pasar un modelo al constructor: falla con "usa valores simples". Pasa el
  id y búscalo en `handle()` (el registro puede haber cambiado).
- Parámetros del constructor que no son `public`: el job no se puede
  guardar.
- Jobs que no aguantan correr dos veces: si el worker se cae a la mitad,
  el job se vuelve a tomar pasados `QUEUE_RETRY_AFTER` segundos (300).
  Hazlos idempotentes (por ejemplo, revisa si el correo ya se envió).
- Olvidar el cron o el worker con `QUEUE_DRIVER=database`: los jobs se
  quedan en la tabla. `doctor` muestra cuántos hay pendientes.
