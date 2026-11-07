# Idiomas

**Cuándo:** la app debe verse en más de un idioma, o necesitas mostrar
fechas, números y dinero con el formato de cada país.

El texto en español **es la clave**: escribes `__('Guardar')` y, si el idioma
activo es inglés, sale lo que diga `lang/en.php` para `'Guardar'`. Lo que no
esté traducido sale en español, así que nada se rompe por una traducción
pendiente.

## Pasos

1. Elige los idiomas en `.env`:

   ```env
   APP_LOCALE=es          # idioma por defecto (sus URLs no llevan prefijo)
   APP_LOCALES=es,en      # todos los que acepta la app
   APP_CURRENCY=MXN       # moneda de format_money()
   ```

2. Pon las páginas dentro de `$router->localized()` en `routes/web.php`.
   Cada ruta existe una vez por idioma: `/precios` (español) y
   `/en/precios` (inglés), cada una con su idioma fijo. Buscadores y
   usuarios ven siempre lo mismo en la misma URL.

   ```php
   $router->localized(static function (Router $router): void {
       $router->get('/', [HomeController::class, 'index'])->name('home');
       $router->get('/precios', [PreciosController::class, 'index'])->name('precios');
   });
   ```

   `route('precios')` da `/en/precios` cuando la página actual está en
   inglés: los enlaces y redirecciones se quedan en el idioma del usuario
   sin hacer nada más. `make:resource` agrega su ruta dentro de este bloque.

3. Envuelve los textos visibles con `__()`, en vistas y en controladores:

   ```php
   <h1>{{ __('Mis pedidos') }}</h1>
   <x-input name="total" :label="__('Total')" />
   <p>{{ __('Hola, :nombre', ['nombre' => $user->nombre]) }}</p>
   <p>{{ trans_choice('Un pedido|:count pedidos', $total) }}</p>

   return redirect(route('pedidos.index'))->with('status', __('Pedido guardado.'));
   ```

   - Marcadores con `:nombre`; nunca concatenes partes de una frase.
   - Plurales: `'uno|varios'` o `'{0} ninguno|uno|varios'`; `:count` se
     reemplaza solo.
   - Frase con HTML (un `<code>`, un enlace): traduce la frase entera con un
     marcador y usa `{!! __('Lee :archivo.', ['archivo' => '<code>x</code>']) !!}`.
     Solo con HTML que escribiste tú, nunca con datos del usuario.

4. Agrega las traducciones en `lang/en.php` (o en `lang/en/*.php` para
   repartirlas por módulo):

   ```php
   return [
       'Mis pedidos' => 'My orders',
       'Hola, :nombre' => 'Hi, :nombre',
       'Un pedido|:count pedidos' => 'One order|:count orders',
       'total' => 'total', // nombre del campo en los errores de validación
   ];
   ```

   Los mensajes del framework (validación, errores, paginación) ya vienen en
   inglés; si pones la misma clave en `lang/en.php`, gana la tuya.

5. En el `<head>` del layout ya están `lang="{{ locale() }}"` y
   `{{ hreflang_links() }}` (enlaces `alternate` con la URL completa de
   cada idioma, para SEO). Revisa que `APP_URL` sea la URL pública real.

6. Para cambiar de idioma, enlaza a la misma página en el otro:

   ```php
   <a href="{{ locale_url('en') }}" hreflang="en">English</a>
   <a href="{{ locale_url('es') }}" hreflang="es">Español</a>
   ```

## Fechas, números y dinero

Salen con el formato del idioma activo (región en `config/app.php`,
`'regions' => ['es' => 'es_MX', 'en' => 'en_US']`):

```php
{{ format_date($pedido->created_at) }}            {{-- 7 nov 2026 / Nov 7, 2026 --}}
{{ format_date($pedido->created_at, 'long', true) }} {{-- con hora --}}
{{ format_number(1234567.891, 2) }}               {{-- 1,234,567.89 --}}
{{ format_money($pedido->total) }}                {{-- $1,234.50 (APP_CURRENCY) --}}
{{ format_money(99, 'USD') }}
```

Usan la extensión `intl` si está; si no (pasa en muchos hostings), un
formato propio para español e inglés.

## Páginas sin prefijo

Para un panel interno que no necesita URLs por idioma, usa el middleware
`locale` sin parámetro: toma `?lang=en` (y lo guarda en la sesión), luego la
sesión, luego el idioma del navegador y al final `APP_LOCALE`. Va después de
la sesión:

```php
$router->group(['middleware' => ['auth', 'locale']], function (Router $router): void { ... });
```

## Test

```php
it('muestra los pedidos en inglés', function (): void {
    $client = client();
    $client->actingAs(usuario());

    $client->get('/en/pedidos')->assertOk()->assertSee('My orders');
    $client->post('/en/pedidos', [])->assertRedirect('/en/pedidos/create');
});
```

## Errores comunes

- Traducir solo la vista y olvidar los mensajes flash del controlador.
- Partir una frase en pedazos (`__('Lee') . ' ' . $x`): en otro idioma el
  orden cambia. Usa una frase con marcadores.
- Cambiar el texto en español de un `__()` sin cambiar la clave en
  `lang/en.php`: la traducción deja de encontrarse y sale en español.
- Rutas fuera de `localized()`: en `/en/...` dan 404 y los enlaces en
  inglés mandan a la versión en español.
- Las rutas (`/precios`) son las mismas en todos los idiomas; solo cambia el
  prefijo. Si necesitas URLs traducidas, defínelas a mano fuera de
  `localized()` con `->middleware('locale:en')`.
