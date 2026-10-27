# Enviar un correo

**Cuándo:** bienvenida, aviso de un pedido, recuperación de contraseña.

Por ahora hay dos drivers (`config/mail.php`): `log` escribe el correo en
`storage/logs` (desarrollo) y `array` lo guarda en memoria (tests). El envío
por SMTP llega en una versión posterior: no instales librerías para eso sin
preguntar.

## Pasos

```php
use BootZen\Mail\Message;

mailer()->send(new Message(
    $cliente->email,
    'Tu pedido está en camino',
    "Hola {$cliente->nombre},\n\nTu pedido #{$pedido->id} salió hoy.\n\nGracias por tu compra."
));
```

- `Message($para, $asunto, $texto, $html = null)`.
- Arma los enlaces con `url(route('pedidos.show', ['id' => $pedido->id]))`
  (`route()` da solo el path; `url()` le agrega `APP_URL`).
- Para textos largos, arma el cuerpo en un método privado del controlador o
  en una clase en `app/` (por ejemplo `app/Mail/PedidoEnviado.php` con un
  método que devuelve el `Message`).

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
        ->and($enviados[0]->text)->toContain('salió hoy');
});
```

## Errores comunes

- Poner contraseñas o tokens en el texto del log: con `MAIL_DRIVER=log`
  todo el correo queda en `storage/logs`.
- Decir en la respuesta si un correo existe o no (en recuperación de
  contraseña responde siempre lo mismo).
