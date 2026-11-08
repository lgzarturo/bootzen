<x-layouts.admin :title="__('Tareas')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ __('Tareas') }}</h1>
        <div class="flex gap-2">
            <x-button :href="route('admin.tareas.export') . '?' . http_build_query($listado->parameters())" variant="ghost">{{ __('Exportar CSV') }}</x-button>
            <x-button :href="route('admin.tareas.create')">{{ __('Nuevo') }}</x-button>
        </div>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <input type="hidden" name="sort" value="{{ $listado->sort() }}">
        <input type="hidden" name="dir" value="{{ $listado->direction() }}">
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Buscar') }}
            <input type="search" name="q" value="{{ $listado->term() }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
        </label>
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Hecha') }}
            <select name="filtro[hecha]" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
                <option value="">{{ __('Todos') }}</option>
                <option value="1" @if ($listado->filter('hecha') === '1') selected @endif>{{ __('Sí') }}</option>
                <option value="0" @if ($listado->filter('hecha') === '0') selected @endif>{{ __('No') }}</option>
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Vence') }} {{ __('desde') }}
            <input type="date" name="filtro[vence_desde]" value="{{ $listado->filter('vence_desde') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
        </label>
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('hasta') }}
            <input type="date" name="filtro[vence_hasta]" value="{{ $listado->filter('vence_hasta') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
        </label>
        <x-button type="submit">{{ __('Filtrar') }}</x-button>
        <a href="{{ route('admin.tareas.index') }}" class="py-2 text-sm text-gray-600 hover:underline">{{ __('Limpiar') }}</a>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="id" label="ID" /></th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="titulo" :label="__('Título')" /></th>
                    <th class="px-4 py-3 font-medium">{{ __('Dueño') }}</th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="hecha" :label="__('Hecha')" /></th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="vence" :label="__('Vence')" /></th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($tareas as $tarea)
                <tr>
                    <td class="px-4 py-3 text-gray-500">{{ $tarea->id }}</td>
                    <td class="px-4 py-3">{{ $tarea->titulo }}</td>
                    <td class="px-4 py-3">{{ $duenos[$tarea->user_id] ?? '' }}</td>
                    <td class="px-4 py-3">{{ $tarea->hecha ? __('Sí') : __('No') }}</td>
                    <td class="px-4 py-3">{{ $tarea->vence?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <x-button :href="route('admin.tareas.edit', ['id' => $tarea->id])" variant="ghost" size="sm">{{ __('Editar') }}</x-button>
                        <x-form :action="route('admin.tareas.destroy', ['id' => $tarea->id])" method="DELETE" class="inline" data-confirm="{{ __('¿Borrar este registro? No se puede deshacer.') }}">
                            <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">{{ __('Borrar') }}</x-button>
                        </x-form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No hay registros que coincidan.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$tareas" />
</x-layouts.admin>
