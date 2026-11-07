<x-layouts.app :title="__('Nueva tarea')">
    <x-card :title="__('Nueva tarea')" class="mx-auto max-w-2xl">
        <x-form :action="route('tareas.store')">
            <x-input name="titulo" :label="__('Título')" required autofocus />
            <x-textarea name="notas" :label="__('Notas')" />
            <x-input name="vence" type="date" :label="__('Vence')" />
            <x-checkbox name="hecha" :label="__('Ya está hecha')" />
            <div class="flex items-center gap-3">
                <x-button type="submit">{{ __('Guardar') }}</x-button>
                <x-button :href="route('tareas.index')" variant="ghost">{{ __('Cancelar') }}</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.app>
