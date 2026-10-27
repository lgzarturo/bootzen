{{--
    Página de error genérica. Variables: $status (int), $title (string), $message (string).
    Para una página propia de un código, crea errors/{codigo}.php (ver 404.php).
    Si esta vista falla, BootZen muestra una página mínima integrada.
--}}
<x-layouts.app :title="$status . ' · ' . $title">
    <div class="py-16 text-center">
        <p class="text-6xl font-bold text-gray-400">{{ $status }}</p>
        <h1 class="mt-4 text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-2 text-gray-600">{{ $message }}</p>
        <div class="mt-8">
            <x-button href="/" variant="ghost">Volver al inicio</x-button>
        </div>
    </div>
</x-layouts.app>
