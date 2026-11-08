<x-layouts.admin :title="__('Usuarios')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ __('Usuarios') }}</h1>
        <x-button :href="route('admin.usuarios.export') . '?' . http_build_query($listado->parameters() + ['rol' => $rol])" variant="ghost">{{ __('Exportar CSV') }}</x-button>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <input type="hidden" name="sort" value="{{ $listado->sort() }}">
        <input type="hidden" name="dir" value="{{ $listado->direction() }}">
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Buscar') }}
            <input type="search" name="q" value="{{ $listado->term() }}" placeholder="{{ __('Nombre o correo') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
        </label>
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Rol') }}
            <select name="rol" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($roles as $opcion)
                    <option value="{{ $opcion }}" @if ($rol === $opcion) selected @endif>{{ $opcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm text-gray-600">
            {{ __('Verificado') }} {{ __('desde') }}
            <input type="date" name="filtro[email_verified_at_desde]" value="{{ $listado->filter('email_verified_at_desde') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
        </label>
        <x-button type="submit">{{ __('Filtrar') }}</x-button>
        <a href="{{ route('admin.usuarios.index') }}" class="py-2 text-sm text-gray-600 hover:underline">{{ __('Limpiar') }}</a>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="id" label="ID" /></th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="nombre" :label="__('Nombre')" /></th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="email" :label="__('Correo')" /></th>
                    <th class="px-4 py-3 font-medium"><x-admin.sort-link :listado="$listado" column="role" :label="__('Rol')" /></th>
                    <th class="px-4 py-3 font-medium">{{ __('Verificado') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($usuarios as $usuario)
                <tr>
                    <td class="px-4 py-3 text-gray-500">{{ $usuario->id }}</td>
                    <td class="px-4 py-3">{{ $usuario->nombre }}</td>
                    <td class="px-4 py-3">{{ $usuario->email }}</td>
                    <td class="px-4 py-3"><span class="{{ class_list(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-blue-100 text-blue-800' => $usuario->role === 'admin', 'bg-gray-100 text-gray-700' => $usuario->role !== 'admin']) }}">{{ $usuario->role }}</span></td>
                    <td class="px-4 py-3">{{ $usuario->email_verified_at ? format_date($usuario->email_verified_at, 'short') : __('No') }}</td>
                    <td class="px-4 py-3 text-right">
                        <x-button :href="route('admin.usuarios.edit', ['id' => $usuario->id])" variant="ghost" size="sm">{{ __('Editar') }}</x-button>
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

    <x-pagination :paginator="$usuarios->appends(['rol' => $rol])" />
</x-layouts.admin>
