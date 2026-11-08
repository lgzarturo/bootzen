<x-layouts.admin :title="__('Nuevo') . ': ' . __('Tarea')">
    <x-card :title="__('Nuevo') . ': ' . __('Tarea')" class="max-w-2xl">
        <x-form :action="route('admin.tareas.store')">
            <x-input name="titulo" type="text" :label="__('Título')" required />
            <x-textarea name="notas" :label="__('Notas')" />
            <x-checkbox name="hecha" :label="__('Hecha')" />
            <x-input name="vence" type="date" :label="__('Vence')" />
            <div class="flex items-center gap-3">
                <x-button type="submit">{{ __('Guardar') }}</x-button>
                <x-button :href="route('admin.tareas.index')" variant="ghost">{{ __('Cancelar') }}</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.admin>
