# Panel de administración

**Cuándo:** "un panel para administrar productos", "que el admin pueda ver y
editar todo", "exportar la lista a Excel".

Necesita usuarios y el rol `admin` (proyecto creado con `--auth`). Cada
pantalla es código generado y editable: nada se arma solo en tiempo de
ejecución.

## Pasos

1. Genera el panel del modelo (si el modelo no existe, también crea la
   migración, el modelo y su factory):

   ```bash
   php bin/bootzen make:admin Producto nombre:string precio:float activo:bool alta:date:nullable
   php bin/bootzen migrate
   composer check
   ```

2. Nombra al primer admin y entra a `/admin/productos`:

   ```bash
   php bin/bootzen user:role ana@correo.com admin
   ```

## Qué crea

- `app/Controllers/Admin/ProductoController.php`: lista, alta, edición,
  borrado y `export` (CSV). Búsqueda, filtros y orden están en `listado()`.
- `resources/views/admin/productos/{index,create,edit}.php` con el layout
  `<x-layouts.admin>` (barra lateral) y `<x-admin.sort-link>`.
- `tests/Feature/AdminProductoTest.php`: acceso solo para admins, lista,
  alta, edición, borrado y CSV.
- Rutas en el grupo `/admin` de `routes/web.php`, con
  `['auth', 'verified', 'role:admin']`, y el enlace en `config/admin.php`
  (`menu`), donde cambias el orden o la etiqueta.

## El listado

```php
private function listado(Request $request): Listing
{
    return Listing::from(Producto::query(), $request)
        ->search(['nombre', 'descripcion'])                 // ?q=
        ->filters(['activo' => 'bool', 'alta' => 'date'])  // ?filtro[activo]=1, ?filtro[alta_desde]=...
        ->sortable(['id', 'nombre', 'precio', 'created_at']); // ?sort=precio&dir=asc
}
```

- Solo se busca, filtra y ordena por las columnas de estas listas; un
  `sort` o un filtro que no esté se ignora.
- `$listado->paginate(20)` conserva búsqueda, orden y filtros en los enlaces.
- `$listado->csv('productos.csv', ['id' => 'ID', 'nombre' => __('Nombre')])`
  exporta todo lo que coincide (no solo la página) en streaming, con BOM
  para Excel y sin fórmulas peligrosas.
- Para un filtro que no es booleano ni fecha (un rol, una categoría),
  agrega el `where` a la consulta antes de `Listing::from()`, validando el
  valor contra una lista (ver `Admin\UserController` del demo).

## Ajustes comunes

- Mostrar un dato de otra tabla (el dueño, la categoría): carga los
  relacionados de la página en una sola consulta con `whereIn`, no uno por
  fila (ver `Admin\TareaController` del demo).
- Un campo que el admin no debe tocar (como `role`): no lo pongas en
  `$fillable` y asígnalo a mano en el controlador, validado.
- Más roles con acceso: cambia `role:admin` del grupo o usa
  `can:productos.*` con permisos en `config/auth.php`.

## Errores comunes

- Usar `make:admin` sin `--auth`: se detiene y lo explica.
- Ordenar o filtrar por columnas que vienen de la petición sin lista blanca.
- Exportar con `->get()` y armar el CSV en memoria: usa `csv()`, que va
  por bloques con `cursor()`.
- Olvidar que el panel se ve solo con `role:admin`: en los tests usa
  `User::factory()->admin()->create()`.
