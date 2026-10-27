<x-layouts.app title="Mis tareas">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Mis tareas</h1>
        <x-button :href="route('tareas.create')">Nueva tarea</x-button>
    </div>

    <nav class="mb-4 flex gap-2 text-sm" aria-label="Filtrar tareas">
        @foreach (['' => 'Todas', 'pendientes' => 'Pendientes', 'hechas' => 'Hechas'] as $valor => $texto)
            <a href="{{ route('tareas.index', $valor === '' ? [] : ['estado' => $valor]) }}" class="{{ class_list([
                'rounded-full px-3 py-1',
                'bg-blue-600 text-white' => ($estado ?? '') === $valor,
                'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' => ($estado ?? '') !== $valor,
            ]) }}">{{ $texto }}</a>
        @endforeach
    </nav>

    <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        @foreach ($tareas as $tarea)
            <li class="flex flex-wrap items-center gap-4 px-4 py-3">
                <x-form :action="route('tareas.completar', ['id' => $tarea->id])" method="PATCH" class="contents">
                    <button type="submit" class="{{ class_list([
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2',
                        'border-green-600 bg-green-600 text-white' => $tarea->hecha,
                        'border-gray-300 hover:border-green-600' => ! $tarea->hecha,
                    ]) }}" aria-label="{{ $tarea->hecha ? 'Marcar como pendiente' : 'Marcar como hecha' }}">
                        @if ($tarea->hecha)✓@endif
                    </button>
                </x-form>
                <div class="min-w-0 flex-1">
                    <p class="{{ class_list(['font-medium', 'text-gray-400 line-through' => $tarea->hecha]) }}">{{ $tarea->titulo }}</p>
                    @if ($tarea->vence)
                        <p class="{{ class_list(['text-xs', 'text-red-600' => $tarea->vencida(), 'text-gray-500' => ! $tarea->vencida()]) }}">Vence el {{ $tarea->vence->format('d/m/Y') }}</p>
                    @endif
                </div>
                <x-button :href="route('tareas.edit', ['id' => $tarea->id])" variant="ghost" size="sm">Editar</x-button>
                <x-button type="button" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50" data-modal-open="borrar-{{ $tarea->id }}">Borrar</x-button>
                <x-modal id="borrar-{{ $tarea->id }}" title="¿Borrar esta tarea?">
                    «{{ $tarea->titulo }}» se borra para siempre.
                    <x-slot:actions>
                        <x-button type="button" variant="ghost" data-modal-close>Cancelar</x-button>
                        <x-form :action="route('tareas.destroy', ['id' => $tarea->id])" method="DELETE">
                            <x-button type="submit" variant="danger">Borrar</x-button>
                        </x-form>
                    </x-slot:actions>
                </x-modal>
            </li>
        @empty
            <li class="px-4 py-10 text-center text-gray-500">No hay tareas aquí. Crea la primera.</li>
        @endforeach
    </ul>

    <x-pagination :paginator="$tareas" />
</x-layouts.app>
