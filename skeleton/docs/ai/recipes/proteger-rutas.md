# Proteger rutas

**Cuándo:** "solo usuarios logueados", "cada usuario ve solo sus registros".

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

   /** Busca el registro solo entre los del usuario: si es de otro, 404. */
   private function find(int $id): Cliente
   {
       return Cliente::where('user_id', auth()->id())->where('id', $id)->firstOrFail();
   }
   ```

   Usa `$this->find($id)` en `show`, `edit`, `update` y `destroy`. Nunca
   tomes `user_id` del formulario.

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
