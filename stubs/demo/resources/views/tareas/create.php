<x-layouts.app title="Nueva tarea">
    <x-card title="Nueva tarea" class="mx-auto max-w-2xl">
        <x-form :action="route('tareas.store')">
            <x-input name="titulo" label="Título" required autofocus />
            <x-textarea name="notas" label="Notas" />
            <x-input name="vence" type="date" label="Vence" />
            <x-checkbox name="hecha" label="Ya está hecha" />
            <div class="flex items-center gap-3">
                <x-button type="submit">Guardar</x-button>
                <x-button :href="route('tareas.index')" variant="ghost">Cancelar</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.app>
