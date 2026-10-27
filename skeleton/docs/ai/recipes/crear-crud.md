# Crear un CRUD

**Cuándo:** "agrega un CRUD de clientes con nombre, email y teléfono".

## Pasos

1. Genera la rebanada completa. Elige el tipo de cada campo (`string`,
   `text`, `email`, `int`, `float`, `bool`, `date`, `datetime`) y marca los
   opcionales con `:nullable`:

   ```bash
   php bin/bootzen make:resource Cliente nombre:string email:email telefono:string:nullable
   ```

   `Cliente` → `clientes` sale bien solo. Usa `--table` cuando el plural en
   inglés no sirva (`Ciudad` → `ciudads`, así que `--table=ciudades`).
2. `php bin/bootzen migrate` y `composer check`. Debe pasar sin tocar nada.
3. Ajusta lo que pida la tarea, en este orden:
   - Reglas en `rules()` del controlador (por ejemplo `email` único:
     `'required|email|max:255|unique:clientes,email'`; al editar, ignora el
     propio registro con un método aparte o `unique:clientes,email,{$id}`).
   - Columnas del listado en `resources/views/clientes/index.php`.
   - Textos y mensajes flash.
4. Agrega a `tests/Feature/ClienteTest.php` un test por cada regla nueva y
   vuelve a correr `composer check`.

## Archivos que crea

- `database/migrations/AAAA_MM_DD_HHMMSS_crear_clientes.php`
- `app/Models/Cliente.php`
- `app/Controllers/ClienteController.php`
- `resources/views/clientes/{index,create,edit,show}.php`
- `tests/Feature/ClienteTest.php`
- La línea `$router->resource('clientes', ClienteController::class);` en `routes/web.php`

## Email único que ignora el registro al editar

```php
public function update(Request $request, int $id): Response
{
    $cliente = Cliente::findOrFail($id);
    $cliente->update($request->validate([
        'nombre' => 'required|string|max:255',
        'email' => "required|email|max:255|unique:clientes,email,{$id}",
    ]));

    return redirect(route('clientes.show', ['id' => $cliente->id]))->with('status', 'Registro actualizado.');
}
```

## Errores comunes

- Escribir el CRUD a mano: tarda más y se aleja del estilo del proyecto.
- Editar una migración que ya corrió: crea otra (ver `migracion.md`).
- Olvidar `migrate` antes de abrir la página: "no such table".
- En tests, crear modelos antes de `client()`: cada `client()` es una base
  nueva y vacía.
