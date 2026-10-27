---
name: crear-componente
description: Crea un componente de vista <x-nombre> (con JavaScript nativo si es interactivo) en este proyecto BootZen. Úsala cuando pidan un modal, un badge, una tarjeta reutilizable o cualquier pieza de interfaz que se repite.
---

# Crear un componente

1. Revisa `resources/views/components/`: si ya hay uno que sirve
   (`x-card`, `x-button`, `x-alert`, `x-form`, `x-input`, `x-textarea`,
   `x-checkbox`, `x-pagination`), úsalo.
2. `php bin/bootzen make:component nombre` (con puntos para carpetas:
   `forms.select`; `--class` si necesita lógica en PHP).
3. Declara las props con `@props([...])`, pinta el contenido con
   `{{ $slot }}` y deja pasar los atributos con
   `$attributes->merge(['class' => '...'])`.
4. Si es interactivo, crea `public/assets/js/components/nombre.js` que
   exporte `default function (elemento, props)` y marca la raíz con
   `data-component="nombre"`. Nada de `<script>` en la vista.
5. Úsalo en la vista que lo necesita y agrega un test de esa página con
   `client()->get(...)->assertSee(...)`.
6. `composer check` en verde.

Ejemplo completo (modal de confirmación de borrado con `<dialog>`):
`docs/ai/recipes/crear-componente.md`. Escribe las clases de Tailwind
completas, nunca armadas por concatenación.
