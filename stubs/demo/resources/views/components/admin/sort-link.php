{{--
    Encabezado de columna que ordena el listado del panel. Uso:
    <x-admin.sort-link :listado="$listado" column="precio" :label="__('Precio')" />
--}}
@props(['listado', 'column', 'label'])
<a href="{{ $listado->sortUrl($column) }}" class="inline-flex items-center gap-1 hover:text-gray-900">
    {{ $label }}
@if ($listado->sort() === $column)
    <span aria-hidden="true">{{ $listado->direction() === 'asc' ? '↑' : '↓' }}</span>
@endif
</a>
