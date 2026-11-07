# Migrar de la v1 a la v2

La v1 de BootZen fue una versión preliminar: el framework venía en un `.phar`,
dependía de varias librerías de Composer y npm, y no tenía base de datos,
sesión ni validación. La v2 es otra cosa por dentro, así que no hay un
comando que convierta un proyecto. La forma corta y segura es **crear un
proyecto v2 y mover tu código**. En una app pequeña de la v1 es una tarde de
trabajo.

## Qué cambió

| En la v1                                              | En la v2                                                                 |
| ----------------------------------------------------- | ------------------------------------------------------------------------ |
| `bootzen.phar` cargado desde `public/index.php`       | `framework/` como código fuente dentro del proyecto                      |
| `init_project.sh`                                     | `bootzen-new mi-app` (`init_project.sh` sigue funcionando y lo llama)     |
| Tu código en `src/` con el namespace `BootZen\`       | Tu código en `app/` con el namespace `App\`; `BootZen\` es solo el framework |
| Vistas en `views/` y componentes como clases que devuelven HTML (`Alert`, `Button`, `Card`...) | Vistas en `resources/views/` y componentes `<x-alert>`, `<x-button>`, `<x-card>`... |
| `vlucas/phpdotenv`, `monolog/monolog`, `predis/predis`, `symfony/var-dumper` | Nada en `require`: `.env`, log, caché y `dd()` vienen en el framework     |
| Tailwind con npm, Husky y lint-staged                 | `php bin/bootzen css:build` con el binario standalone, sin Node           |
| `redirect()`, `abort()`, `json_response()` y `download()` cortaban la ejecución con `exit` | Devuelven un `Response` (o lanzan una excepción): hay que hacer `return` |
| Sin base de datos                                     | SQLite por defecto (MySQL opcional), migraciones y modelos               |
| Sin sesión, CSRF ni validación                        | Incluidas; `--auth` agrega registro, login y recuperación de contraseña  |

## Paso a paso

1. **Actualiza tu instalación** de BootZen (deja el clon en `~/.bootzen` en la
   última versión):

   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/updater.sh)"
   ```

2. **Crea el proyecto nuevo** junto al viejo. Usa `--auth` si tu app va a tener
   usuarios:

   ```bash
   bootzen-new mi-app-v2
   ```

3. **Controladores**: copia `src/Controllers/*.php` a `app/Controllers/` y
   cambia `namespace BootZen\Controllers;` por `namespace App\Controllers;`. Lo
   mismo con middleware (`app/Middleware/`, `App\Middleware`) y cualquier otra
   clase tuya.

4. **Rutas**: la API del router es la misma (`get`, `post`, `group`,
   `middleware`, `[Controlador::class, 'metodo']`). Pásalas a `routes/web.php`
   (páginas, con sesión y CSRF) o a `routes/api.php` (JSON con prefijo `/api`).
   Agrega `->name('...')` para poder usar `route('nombre')`.

5. **Respuestas**: busca `redirect(`, `abort(`, `json_response(` y `download(`
   y asegúrate de devolver su resultado:

   ```php
   // v1
   redirect('/gracias');

   // v2
   return redirect('/gracias');
   ```

   Un controlador puede devolver un `Response`, un array (sale como JSON) o un
   string.

6. **Vistas**: mueve `views/` a `resources/views/` y devuélvelas con
   `return view('carpeta.nombre', ['dato' => $valor]);`. Cambia
   `<?= htmlspecialchars($x) ?>` por `{{ $x }}` y las clases de componentes
   por las etiquetas: `(new Button(['variant' => 'danger'], ['default' => 'Eliminar']))->render()`
   pasa a ser `<x-button variant="danger">Eliminar</x-button>`. Las directivas disponibles
   están en `AGENTS.md`.

7. **Dependencias**: no copies el `composer.json` viejo. Lo que usabas de
   phpdotenv es `env()` dentro de `config/` y `config()` en el resto; Monolog
   pasa a `logger('mensaje', 'error')`; Predis pasa a `cache()` (archivos).
   Si de verdad necesitas una librería, agrégala a `require-dev` o pregúntate
   si el MVP la necesita.

8. **CSS y JS**: copia tus estilos a `resources/css/app.css` (ya importa
   Tailwind v4) y tu JS a `public/assets/js/`. Borra `package.json`,
   `tailwind.config.js` y la configuración de Husky.

9. **Datos**: si tenías archivos JSON o arrays haciendo de base de datos, este
   es buen momento para `php bin/bootzen make:resource` y una migración.

10. **Revisa**: `composer install && composer check` y
    `php bin/bootzen doctor`. Luego despliega con las guías de
    `docs/deploy/` del proyecto.

## Con ayuda de la IA

El proyecto v2 trae `AGENTS.md`, recetas y la referencia de la API. Un pedido
que funciona bien:

> Estoy migrando una app de BootZen v1 a v2. El proyecto viejo está en
> `../mi-app`. Mueve los controladores, rutas y vistas siguiendo
> `~/.bootzen/docs/MIGRACION-V1-V2.md` y `AGENTS.md`, sin agregar
> dependencias, y deja `composer check` en verde.
