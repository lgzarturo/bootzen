{{--
    Enlaces de paginación. Uso: <x-pagination :paginator="$tareas" />
    $tareas viene de Modelo::query()->paginate($porPagina, $request->integer('page', 1)).
    Para conservar filtros en los enlaces: $tareas->appends(['q' => $busqueda]).
--}}
@props(['paginator'])
@if ($paginator->hasPages())
<nav {{ $attributes->merge(['class' => 'mt-6 flex items-center justify-between text-sm']) }} aria-label="{{ __('Paginación') }}">
@if ($paginator->previousPage())
    <a href="{{ $paginator->url($paginator->previousPage()) }}" rel="prev" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">{{ __('Anterior') }}</a>
@else
    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-400">{{ __('Anterior') }}</span>
@endif
    <span class="text-gray-600">{{ __('Página :actual de :total', ['actual' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}</span>
@if ($paginator->nextPage())
    <a href="{{ $paginator->url($paginator->nextPage()) }}" rel="next" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">{{ __('Siguiente') }}</a>
@else
    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-400">{{ __('Siguiente') }}</span>
@endif
</nav>
@endif
