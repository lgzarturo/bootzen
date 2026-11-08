<x-layouts.admin :title="__('Editar') . ': ' . __('Tarea') . ' #' . $tarea->id">
    <x-card :title="__('Editar') . ': ' . __('Tarea') . ' #' . $tarea->id" class="max-w-2xl">
        <x-form :action="route('admin.tareas.update', ['id' => $tarea->id])" method="PUT">
            <x-input name="titulo" type="text" :label="__('Título')" :value="$tarea->titulo" required />
            <x-textarea name="notas" :label="__('Notas')" :value="$tarea->notas" />
            <x-checkbox name="hecha" :label="__('Hecha')" :checked="$tarea->hecha" />
            <x-input name="vence" type="date" :label="__('Vence')" :value="$tarea->vence?->format('Y-m-d')" />
            <div class="flex items-center gap-3">
                <x-button type="submit">{{ __('Guardar') }}</x-button>
                <x-button :href="route('admin.tareas.index')" variant="ghost">{{ __('Cancelar') }}</x-button>
            </div>
        </x-form>
    </x-card>
</x-layouts.admin>
