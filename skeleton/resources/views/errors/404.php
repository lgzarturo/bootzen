{{-- Página 404 con mensaje propio, salvo que abort(404, 'mensaje') indique otro. --}}
<x-layouts.app title="Página no encontrada">
    <div class="py-16 text-center">
        <p class="text-6xl font-bold text-gray-400">404</p>
        <h1 class="mt-4 text-2xl font-semibold">Página no encontrada</h1>
        <p class="mt-2 text-gray-600">{{ $message === 'Not Found' ? 'La página que buscas no existe o cambió de dirección.' : $message }}</p>
        <div class="mt-8">
            <x-button href="/" variant="ghost">Volver al inicio</x-button>
        </div>
    </div>
</x-layouts.app>
