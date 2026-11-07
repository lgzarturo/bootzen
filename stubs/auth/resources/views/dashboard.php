<x-layouts.app :title="__('Panel')">
    <h1 class="text-3xl font-bold tracking-tight">{{ __('Hola, :nombre', ['nombre' => $user?->nombre]) }}</h1>
    <p class="mt-2 text-gray-600">{{ __('Esta página solo la ven los usuarios con sesión iniciada (middleware auth).') }}</p>
</x-layouts.app>
