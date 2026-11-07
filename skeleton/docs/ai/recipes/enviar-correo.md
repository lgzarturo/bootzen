# Enviar un correo

**Cuándo:** bienvenida, aviso de un pedido, factura adjunta, recuperación de
contraseña.

Drivers (`MAIL_DRIVER` en `.env`, `config/mail.php`):

- `log`: escribe el correo en `storage/logs` (desarrollo). No sale nada.
- `smtp`: envío real a un servidor SMTP. Sin librerías.
- `array`: lo guarda en memoria (tests; `phpunit.xml` ya lo usa).

No instales PHPMailer ni Symfony Mailer: el driver `smtp` cubre STARTTLS,
TLS directo, autenticación, HTML y adjuntos.

## Pasos

```php
use BootZen\Mail\Message;

mailer()->send(new Message(
    $cliente->email,
    'Tu pedido está en camino',
    "Hola {$cliente->nombre},\n\nTu pedido #{$pedido->id} salió hoy.\n\nGracias por tu compra."
));
```

- `Message($para, $asunto, $texto, $html = null)`. Con `$html` el correo
  lleva las dos versiones; el texto es obligatorio igual.
- Opcionales, encadenables:

  ```php
  $correo = (new Message($cliente->email, 'Tu factura', $texto))
      ->cc('contabilidad@tienda.mx')
      ->bcc('archivo@tienda.mx')               // no se ve en las cabeceras
      ->replyTo('cobranza@tienda.mx')
      ->from('facturas@tienda.mx', 'Facturación') // en lugar de MAIL_FROM_ADDRESS
      ->attach(storage_path("app/facturas/{$factura->archivo}"), "Factura-{$factura->folio}.pdf")
      ->attachData($csv, 'detalle.csv', 'text/csv'); // contenido generado en memoria

  mailer()->send($correo);
  ```

- Arma los enlaces con `url(route('pedidos.show', ['id' => $pedido->id]))`
  (`route()` da solo el path; `url()` le agrega `APP_URL`).
- Para textos largos, arma el cuerpo en un método privado del controlador o
  en una clase en `app/` (por ejemplo `app/Mail/PedidoEnviado.php` con un
  método que devuelve el `Message`). Para HTML, `view('correos.pedido', [...])->getBody()`.

## Si el envío falla

`mailer()->send()` lanza `BootZen\Mail\MailException` (servidor caído,
credenciales malas, dirección inválida, adjunto que no existe). Decide en
cada caso:

```php
use BootZen\Mail\MailException;

try {
    mailer()->send($correo);
} catch (MailException $e) {
    logger('No se pudo enviar el aviso del pedido: ' . $e->getMessage(), 'error');
    // el pedido ya se guardó: no rompas la página por un correo
}
```

Si el correo es lo único que hace la acción (por ejemplo "reenviar
factura"), deja que la excepción suba o muestra un error al usuario.

## Configurar SMTP

En `.env`:

```dotenv
MAIL_DRIVER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls        # tls (587, STARTTLS), ssl (465) o none (servidor local)
MAIL_USERNAME=usuario
MAIL_PASSWORD=clave
MAIL_FROM_ADDRESS=hola@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

Pruébalo con `php bin/bootzen mail:test tu@correo.com` y revisa
`php bin/bootzen doctor`.

## Test

```php
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\Mailer;

it('avisa al cliente cuando sale el pedido', function (): void {
    $client = client();
    // ... crea el pedido y dispara la acción

    $mailer = $client->app->make(Mailer::class);
    $enviados = $mailer instanceof ArrayMailer ? $mailer->sent() : [];

    expect($enviados)->toHaveCount(1)
        ->and($enviados[0]->to)->toBe('ana@ejemplo.com')
        ->and($enviados[0]->text)->toContain('salió hoy')
        ->and($enviados[0]->getAttachments())->toHaveCount(0);
});
```

Para probar el caso de error, reemplaza el mailer por uno que falle:
`$client->app->instance(Mailer::class, new class () implements Mailer { public function send(Message $m): void { throw new MailException('caído'); } });`

## Errores comunes

- Poner contraseñas o tokens en el texto del log: con `MAIL_DRIVER=log`
  todo el correo queda en `storage/logs`.
- Decir en la respuesta si un correo existe o no (en recuperación de
  contraseña responde siempre lo mismo).
- Dejar `MAIL_VERIFY_PEER=false` en producción: acepta cualquier
  certificado. Úsalo solo con servidores de prueba como Mailpit.
- Meter datos del usuario en el asunto sin pensar: los saltos de línea se
  rechazan (`MailException`), pero valida antes con `$request->validate()`.
