{{--
    Modal con <dialog>. Se abre con un botón data-modal-open="id" y se cierra con data-modal-close
    (o con un clic fuera). Comportamiento en public/assets/js/components/modal.js. Uso:
    <x-button data-modal-open="borrar-3">Borrar</x-button>
    <x-modal id="borrar-3" title="¿Borrar?">
        Texto
        <x-slot:actions> botones </x-slot:actions>
    </x-modal>
--}}
@props(['id', 'title' => null])
<dialog id="{{ $id }}" data-component="modal" aria-labelledby="{{ $id }}-titulo" {{ $attributes->merge(['class' => 'm-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 shadow-xl backdrop:bg-gray-900/50']) }}>
    <div class="p-6">
@if ($title)
        <h2 id="{{ $id }}-titulo" class="mb-2 text-lg font-semibold text-gray-900">{{ $title }}</h2>
@endif
        <div class="text-sm text-gray-700">{{ $slot }}</div>
@if (isset($actions))
        <div class="mt-6 flex justify-end gap-3">{{ $actions }}</div>
@endif
    </div>
</dialog>
