{{--
    Tarjeta. Uso:
    <x-card title="Pendientes" subtitle="Hoy">
        contenido
        <x-slot:footer> acciones </x-slot:footer>
    </x-card>
    Slots opcionales: header (reemplaza título y subtítulo) y footer.
--}}
@props(['title' => null, 'subtitle' => null, 'image' => null])
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200']) }}>
@if ($image)
    <img src="{{ $image }}" alt="{{ $title ?? '' }}" class="h-48 w-full object-cover">
@endif
    <div class="p-6">
@if (isset($header))
        <div class="mb-4">{{ $header }}</div>
@elseif ($title || $subtitle)
        <div class="mb-4">
@if ($title)
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
@endif
@if ($subtitle)
            <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
@endif
        </div>
@endif
        <div class="text-gray-700">{{ $slot }}</div>
@if (isset($footer))
        <div class="mt-4 border-t border-gray-200 pt-4">{{ $footer }}</div>
@endif
    </div>
</div>
