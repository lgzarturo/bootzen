<x-layouts.admin :title="__('Editar') . ': ' . $usuario->nombre">
    <x-card :title="$usuario->nombre" :subtitle="$usuario->email" class="max-w-xl">
        <x-form :action="route('admin.usuarios.update', ['id' => $usuario->id])" method="PUT">
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                {{ __('Rol') }}
                <select name="role" class="rounded-lg border border-gray-300 px-3 py-2 font-normal text-gray-900">
                    @foreach ($roles as $opcion)
                        <option value="{{ $opcion }}" @if (old('role', $usuario->role) === $opcion) selected @endif>{{ $opcion }}</option>
                    @endforeach
                </select>
                @error('role')
                    <span class="text-sm font-normal text-red-600">{{ $message }}</span>
                @enderror
            </label>
            <x-checkbox name="verificado" :label="__('Correo verificado')" :checked="$usuario->email_verified_at !== null" />
            <div class="flex items-center gap-3">
                <x-button type="submit">{{ __('Guardar') }}</x-button>
                <x-button :href="route('admin.usuarios.index')" variant="ghost">{{ __('Cancelar') }}</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.admin>
