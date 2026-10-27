---
name: crear-recurso
description: Crea un CRUD completo (migración, modelo, controlador con validación, rutas, vistas y tests) en este proyecto BootZen con make:resource. Úsala cuando pidan "agrega un CRUD de...", "una pantalla para administrar...", o una entidad nueva con alta, lista, edición y borrado.
---

# Crear un recurso

1. Decide los campos y sus tipos: `string`, `text`, `email`, `int`, `float`,
   `bool`, `date`, `datetime`; agrega `:nullable` a los opcionales.
2. Genera:

   ```bash
   php bin/bootzen make:resource Cliente nombre:string email:email telefono:string:nullable
   ```

   Si el plural en inglés no sirve (`Ciudad` → `ciudads`), agrega
   `--table=ciudades`. Lee lo que imprime el comando: son los archivos creados.
3. `php bin/bootzen migrate` y `composer check`. Tiene que pasar antes de
   tocar nada.
4. Ajusta según el pedido: reglas en `rules()` del controlador, columnas del
   listado, textos. Si el pedido dice "solo usuarios logueados" o "cada quien
   lo suyo", sigue `docs/ai/recipes/proteger-rutas.md`.
5. Por cada regla o comportamiento nuevo, un test en
   `tests/Feature/<Modelo>Test.php` con `client()`.
6. `composer check` en verde. Si falla, lee el mensaje: dice qué llegó en vez
   de lo esperado.

Detalles y ejemplos: `docs/ai/recipes/crear-crud.md`. No escribas el CRUD a
mano desde cero ni edites `framework/`.
