---
name: revisar-seguridad
description: Revisa los cambios de este proyecto BootZen contra los errores de seguridad típicos (validación, permisos por usuario, SQL, XSS, CSRF, archivos, secretos). Úsala antes de dar por terminada una tarea que toca formularios, rutas, consultas o archivos, o cuando pidan "revisa la seguridad".
---

# Revisar seguridad

Revisa el diff (`git diff` y los archivos nuevos) punto por punto. Por cada
problema, corrígelo y agrega un test que lo cubra.

## Entrada y permisos

- [ ] Toda entrada pasa por `$request->validate([...])` antes de usarse.
- [ ] Las rutas privadas están en un grupo con `middleware => 'auth'`.
- [ ] Los registros de un usuario se buscan con `where('user_id', auth()->id())`
      en **todas** las acciones (index, show, edit, update, destroy, JSON).
- [ ] Nunca se toma `user_id`, `role` o `precio` del formulario si lo decide
      el servidor. `$fillable` no incluye columnas sensibles que el usuario
      no deba cambiar.

## Base de datos

- [ ] Ningún dato del usuario se concatena en SQL: `where()`, `whereRaw('x = ?', [$valor])`.
- [ ] Nada de `PDO` directo.

## Vistas y JavaScript

- [ ] Todo se imprime con `{{ }}`. `{!! !!}` solo con HTML generado por el
      propio código, nunca con datos del usuario.
- [ ] Las URLs que vienen del usuario se validan con `url` antes de usarlas en `href`.
- [ ] Sin `<script>` con lógica en las vistas; sin `innerHTML` con datos del servidor en JS (usa `textContent`).

## Formularios y sesión

- [ ] Cada formulario usa `<x-form>` o lleva `@csrf`. No se agregaron rutas a `csrf_except` sin motivo.
- [ ] Las contraseñas se guardan con `Hash::make()` y nunca se registran en logs.
- [ ] Los formularios sensibles (login, contacto, recuperación) tienen `throttle`.

## Archivos

- [ ] Validación con `file`/`image`/`mimes` y `max`.
- [ ] Se guarda con `store()` (privado) o `storePublic()` (público); nunca con el nombre original.
- [ ] Los archivos privados se entregan desde un controlador que revisa permisos.

## Configuración

- [ ] Sin claves, tokens ni contraseñas en el código: van en `.env` y se leen en `config/`.
- [ ] `APP_DEBUG=false` en producción (`php bin/bootzen doctor` lo revisa).

Al terminar: `composer check` en verde y un resumen de lo que corregiste.
