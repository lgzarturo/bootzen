# Proteger rutas

**Cuándo:** "solo usuarios logueados", "cada usuario ve solo sus registros",
"solo los admin", "los editores pueden publicar".

Necesita autenticación. Si el proyecto no se creó con `--auth`, crea primero
el modelo `User`, el login y el registro siguiendo `app/Controllers/Auth` de
un proyecto `--auth` (o pídelo explícitamente).

## Solo usuarios con sesión

Envuelve las rutas en un grupo con el middleware `auth`. Los invitados van a
la ruta `login` y, al entrar, vuelven a donde iban (`auth()->intended()`).

```php
// routes/web.php
$router->group(['middleware' => 'auth'], static function (Router $router): void {
    $router->resource('clientes', ClienteController::class);
});
```

Las peticiones JSON de invitados reciben 401 en lugar de la redirección.

## Solo con el correo verificado

En proyectos `--auth` las páginas privadas llevan además `verified`: quien no
abrió el enlace del correo va a `/verificar-correo` (en JSON, 403).

```php
$router->group(['middleware' => ['auth', 'verified']], static function (Router $router): void {
    $router->resource('clientes', ClienteController::class);
});
```

En tests, `User::factory()->create()` ya viene verificado; para probar el
caso contrario usa `User::factory()->sinVerificar()->create()`.

## Enlaces firmados (sin sesión)

Para un enlace que se manda por correo y debe funcionar sin iniciar sesión
(darse de baja, descargar una factura), firma la ruta y protégela con
`signed`. Si alguien cambia un parámetro o el enlace venció, responde 403.

```php
$router->get('/facturas/{id}/descargar', [FacturaController::class, 'descargar'])
    ->name('facturas.descargar')
    ->middleware('signed');

$enlace = signed_route('facturas.descargar', ['id' => $factura->id], 60 * 24); // vence en un día
```

## Cada usuario ve solo lo suyo

1. Migración nueva con la columna del dueño:

   ```php
   $db->exec('ALTER TABLE clientes ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE CASCADE');
   ```

2. Agrega `user_id` a `$fillable` y al `@property` del modelo.
3. En el controlador, todo pasa por el dueño:

   ```php
   public function index(Request $request): Response
   {
       $clientes = Cliente::where('user_id', auth()->id())->orderBy('id', 'desc')->paginate(15, $request->integer('page', 1));

       return view('clientes.index', ['clientes' => $clientes]);
   }

   public function store(Request $request): Response
   {
       $cliente = Cliente::create(['user_id' => auth()->id()] + $request->validate($this->rules()));

       return redirect(route('clientes.show', ['id' => $cliente->id]))->with('status', 'Registro creado.');
   }

   /** Busca el registro y pide permiso a ClientePolicy: si no puede, 404. */
   private function find(int $id, string $ability): Cliente
   {
       $cliente = Cliente::findOrFail($id);
       authorize($ability, $cliente, 404);

       return $cliente;
   }
   ```

   Usa `$this->find($id, 'view')` en `show`, `'update'` en `edit` y `update`
   y `'delete'` en `destroy`. Nunca tomes `user_id` del formulario.

4. Crea la política: `php bin/bootzen make:policy Cliente` genera
   `app/Policies/ClientePolicy.php` con `view`, `update` y `delete` que
   comparan `user_id` con el usuario. BootZen la encuentra por el nombre.
5. En las vistas, muestra los botones solo a quien puede:
   `@if (can('update', $cliente)) <x-button ...>Editar</x-button> @endif`.

El admin (rol con `'*'` en `config/auth.php`) pasa todas las políticas: puede
corregir registros ajenos sin cambiar nada.

## Roles y permisos

Cada usuario tiene un rol (columna `role`, por defecto `usuario`). Los
permisos de cada rol van en `config/auth.php`:

```php
'roles' => [
    'admin' => ['*'],                          // todo
    'editor' => ['articulos.*', 'reportes.ver'], // articulos.* cubre articulos.publicar
    'usuario' => [],
],
```

```php
$router->group(['middleware' => ['auth', 'role:admin']], ...);       // solo admin
$router->get('/reportes', ...)->middleware(['auth', 'can:reportes.ver']);
if (can('articulos.publicar')) { ... }                             // en controladores y vistas
$usuario->hasRole('editor');                                        // con el trait Authorizable
```

- El primer admin se nombra desde la terminal:
  `php bin/bootzen user:role ana@correo.com admin`.
- `role` nunca va en `$fillable`: si no, alguien podría registrarse como
  admin mandando `role=admin` en el formulario.
- En tests: `User::factory()->admin()->create()`.

## Test

```php
it('solo deja ver a usuarios con sesión', function (): void {
    $client = client();
    $ana = User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);

    $client->get('/clientes')->assertRedirect('/login');
    $client->actingAs($ana)->get('/clientes')->assertOk();
});

it('no deja ver registros de otro usuario', function (): void {
    $client = client();
    $ana = User::create([...]);
    $beto = User::create([...]);
    $ajeno = Cliente::create(['user_id' => $beto->id, 'nombre' => 'X', 'email' => 'x@ejemplo.com']);

    $client->actingAs($ana)->get('/clientes/' . $ajeno->id)->assertNotFound();
});
```

Los tests que ya existían para esas rutas ahora necesitan `actingAs($usuario)`.

## Errores comunes

- Proteger solo el listado: `show`, `edit`, `update` y `destroy` también.
- Responder 403 a registros ajenos revela que existen; 404 no.
- Comprobar `auth()->check()` a mano en cada método: usa el middleware.
- Poner `role` en `$fillable` o leerlo del formulario.
- Revisar permisos solo en la vista (ocultar el botón): el controlador
  también lleva `authorize()`.
- Armar a mano un enlace "secreto" con el id: cualquiera lo adivina. Usa
  `signed_route()`.
