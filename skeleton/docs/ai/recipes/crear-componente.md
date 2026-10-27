# Crear un componente

**Cuándo:** una pieza de interfaz que se repite (badge, modal, tarjeta de
precio), o algo interactivo.

Antes de crear uno, revisa los que ya existen en
`resources/views/components/`: `<x-layouts.app>`, `<x-navigation>`,
`<x-card>`, `<x-button>`, `<x-alert>`, `<x-form>`, `<x-input>`,
`<x-textarea>`, `<x-checkbox>` y `<x-pagination>`.

## Solo vista

```bash
php bin/bootzen make:component badge          # resources/views/components/badge.php
php bin/bootzen make:component forms.select   # resources/views/components/forms/select.php → <x-forms.select>
```

```php
{{-- resources/views/components/badge.php --}}
@props(['color' => 'gray'])
<span {{ $attributes->class([
    'rounded px-2 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => $color === 'gray',
    'bg-green-100 text-green-800' => $color === 'green',
]) }}>{{ $slot }}</span>
```

- `@props` declara las props; el resto de atributos queda en `$attributes`
  (`class` se mezcla con `->class([...])` o `->merge([...])`).
- Slots con nombre: `<x-slot:footer>...</x-slot:footer>` llega como `$footer`.
- Si necesita lógica en PHP, `make:component badge --class` crea también
  `app/View/Components/Badge.php`.
- `make:component` crea un esqueleto con la prop `title`: reemplázalo por lo
  que necesites (las props de los componentes van en inglés: `title`,
  `variant`, `size`).
- **Clases nuevas de Tailwind**: el CSS de `public/assets/app.css` ya trae las
  de los componentes incluidos, pero una clase que nadie usaba no existe hasta
  recompilar con `php bin/bootzen css:build` (o mientras corre `serve`). La
  primera compilación descarga Tailwind, así que necesita red; sin red, usa
  clases que ya aparezcan en otras vistas.

## Con JavaScript: modal de confirmación

Vista con `<dialog>` nativo:

```php
{{-- resources/views/components/modal.php --}}
@props(['id', 'title' => null])
<dialog id="{{ $id }}" data-component="modal" {{ $attributes->merge(['class' => 'm-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 shadow-xl backdrop:bg-gray-900/50']) }}>
    <div class="w-full max-w-md p-6">
@if ($title)
        <h2 class="mb-2 text-lg font-semibold">{{ $title }}</h2>
@endif
        <div class="text-gray-700">{{ $slot }}</div>
@if (isset($actions))
        <div class="mt-6 flex justify-end gap-3">{{ $actions }}</div>
@endif
    </div>
</dialog>
```

Comportamiento en `public/assets/js/components/modal.js` (BootZen lo carga
solo por el `data-component`):

```js
/**
 * Modal con <dialog>. Ábrelo con un botón data-modal-open="id" y ciérralo con data-modal-close.
 */
export default function modal(dialog) {
  const open = (event) => {
    if (event.target.closest(`[data-modal-open="${dialog.id}"]`)) {
      event.preventDefault();
      dialog.showModal();
    }
  };
  const close = (event) => {
    if (event.target.closest('[data-modal-close]') || event.target === dialog) {
      dialog.close();
    }
  };

  document.addEventListener('click', open);
  dialog.addEventListener('click', close);

  return () => document.removeEventListener('click', open);
}
```

Uso para confirmar un borrado (si la vista ya tenía un botón con
`data-confirm="..."`, reemplázalo por este para que no salgan dos
confirmaciones):

```php
<x-button type="button" variant="danger" data-modal-open="borrar-{{ $cliente->id }}">Borrar</x-button>
<x-modal id="borrar-{{ $cliente->id }}" title="¿Borrar este cliente?">
    No se puede deshacer.
    <x-slot:actions>
        <x-button type="button" variant="ghost" data-modal-close>Cancelar</x-button>
        <x-form :action="route('clientes.destroy', ['id' => $cliente->id])" method="DELETE">
            <x-button type="submit" variant="danger">Borrar</x-button>
        </x-form>
    </x-slot:actions>
</x-modal>
```

## Test

Los componentes se prueban a través de la página que los usa:

```php
client()->get('/clientes/1')->assertOk()->assertSee('data-modal-open="borrar-1"', false);
```

## Errores comunes

- `<script>` con lógica dentro de la vista: va en `public/assets/js/components/`.
- Clases de Tailwind armadas por concatenación (`'bg-' . $color . '-100'`):
  Tailwind no las encuentra. Escríbelas completas, como en el `badge`.
- Copiar el HTML de un componente en vez de usarlo.
