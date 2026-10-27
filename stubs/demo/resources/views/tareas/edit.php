<x-layouts.app title="Editar tarea">
    <x-card title="Editar tarea" class="mx-auto max-w-2xl">
        <x-form :action="route('tareas.update', ['id' => $tarea->id])" method="PUT">
            <x-input name="titulo" label="Título" :value="$tarea->titulo" required />
            <x-textarea name="notas" label="Notas" :value="$tarea->notas" />
            <x-input name="vence" type="date" label="Vence" :value="$tarea->vence?->format('Y-m-d')" />
            <x-checkbox name="hecha" label="Ya está hecha" :checked="$tarea->hecha" />
            <div class="flex items-center gap-3">
                <x-button type="submit">Guardar</x-button>
                <x-button :href="route('tareas.index')" variant="ghost">Cancelar</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.app>
