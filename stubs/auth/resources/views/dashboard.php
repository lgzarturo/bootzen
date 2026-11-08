<x-layouts.app :title="__('Panel')">
    <h1 class="text-3xl font-bold tracking-tight">{{ __('Hola, :nombre', ['nombre' => $user?->nombre]) }}</h1>
    <p class="mt-2 text-gray-600">{{ __('Esta página solo la ven los usuarios con sesión iniciada y el correo verificado (middleware auth y verified).') }}</p>

    <x-card :title="__('Sesiones')" :subtitle="__('Si iniciaste sesión en otro equipo y ya no lo usas, ciérrala desde aquí.')" class="mt-8 max-w-xl">
        <x-form :action="route('sessions.destroy-others')">
            <x-input name="password" type="password" :label="__('Contraseña actual')" autocomplete="current-password" required />
            <x-button type="submit" variant="ghost">{{ __('Cerrar sesión en los demás dispositivos') }}</x-button>
        </x-form>
    </x-card>
</x-layouts.app>
