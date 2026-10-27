{{--
    Botón o enlace con estilo de botón. Uso:
    <x-button>Guardar</x-button>
    <x-button variant="danger" type="submit">Borrar</x-button>
    <x-button href="{{ route('tareas.create') }}" size="sm">Nueva</x-button>
    variant: primary, secondary, danger, ghost · size: sm, md, lg
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@if ($href)
<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2',
    'px-3 py-1.5 text-sm' => $size === 'sm',
    'px-5 py-2.5 text-sm' => $size === 'md',
    'px-7 py-3 text-base' => $size === 'lg',
    'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500' => $variant === 'primary',
    'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500' => $variant === 'secondary',
    'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500' => $variant === 'danger',
    'bg-transparent text-blue-600 hover:bg-blue-50 focus:ring-blue-500' => $variant === 'ghost',
]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class([
    'inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    'px-3 py-1.5 text-sm' => $size === 'sm',
    'px-5 py-2.5 text-sm' => $size === 'md',
    'px-7 py-3 text-base' => $size === 'lg',
    'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500' => $variant === 'primary',
    'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500' => $variant === 'secondary',
    'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500' => $variant === 'danger',
    'bg-transparent text-blue-600 hover:bg-blue-50 focus:ring-blue-500' => $variant === 'ghost',
]) }}>{{ $slot }}</button>
@endif
