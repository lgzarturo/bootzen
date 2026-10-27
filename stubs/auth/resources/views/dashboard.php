<x-layouts.app title="Panel">
    <h1 class="text-3xl font-bold tracking-tight">Hola, {{ $user?->nombre }}</h1>
    <p class="mt-2 text-gray-600">Esta página solo la ven los usuarios con sesión iniciada (middleware auth).</p>
</x-layouts.app>
