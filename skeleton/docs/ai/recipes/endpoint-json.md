# Endpoint JSON

**Cuándo:** "un endpoint que liste los clientes en JSON con paginación".

## ¿web.php o api.php?

- **Datos privados o para el JS de la propia app** → `routes/web.php`, dentro
  del grupo `auth` si hace falta. La sesión del navegador sirve de
  autenticación y `request()` de `bootzen.js` manda el CSRF.
- **Datos públicos para otras aplicaciones** → `routes/api.php` (prefijo
  `/api`, sin sesión, CORS y 60 peticiones por minuto por IP). Todavía no hay
  tokens de API: no pongas ahí datos privados.

## Pasos

1. Un controlador aparte (`php bin/bootzen make:controller ClienteJson --json`
   lo crea sin vista) o un método nuevo. Devolver un `Paginator` (o un
   modelo, o un array) responde JSON:

   ```php
   use BootZen\Database\Paginator;

   /**
    * @return Paginator<Cliente>
    */
   public function index(Request $request): Paginator
   {
       $porPagina = min(max($request->integer('per_page', 15), 1), 100);

       return Cliente::query()->orderBy('id', 'desc')->paginate($porPagina, $request->integer('page', 1));
   }
   ```

   La respuesta es
   `{"data": [...], "total": 42, "per_page": 15, "current_page": 1, "last_page": 3}`.
   Las columnas de `$hidden` no salen. El `@return Paginator<Cliente>` es
   obligatorio para PHPStan (el paginador es genérico).

2. La ruta, con nombre:

   ```php
   // routes/web.php, junto al CRUD protegido
   $router->get('/clientes.json', [ClienteJsonController::class, 'index'])->name('clientes.json');
   ```

   (o en `routes/api.php`: `$router->get('/clientes', ...)->name('clientes.index')`
   queda como `GET /api/clientes`, nombre `api.clientes.index`).

3. Para filtrar, valida antes de usar: `$request->validate(['q' => 'nullable|string|max:100'])`
   y `->where('nombre', 'like', '%' . $q . '%')` (el valor va como parámetro).

## Test

```php
it('lista los clientes en JSON con paginación', function (): void {
    $client = client();
    foreach (range(1, 20) as $i) {
        Cliente::create(['nombre' => "Cliente {$i}", 'email' => "c{$i}@ejemplo.com"]);
    }

    $client->getJson('/clientes.json?page=2&per_page=5')
        ->assertOk()
        ->assertJson(['total' => 20, 'per_page' => 5, 'current_page' => 2, 'last_page' => 4])
        ->assertJsonCount(5, 'data');
});
```

Si la ruta está bajo `auth`, el test necesita `actingAs($usuario)` y un
invitado recibe 401: `client()->getJson('/clientes.json')->assertUnauthorized()`.

## Errores comunes

- Armar el JSON con `json_encode` a mano: retorna el paginador o el array.
- Aceptar `per_page` sin tope: alguien pide 1.000.000.
- Poner datos privados en `api.php` pensando que la sesión los protege: ahí
  no hay sesión.
