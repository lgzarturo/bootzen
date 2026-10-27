{{-- Token CSRF vencido o ausente: la página quedó abierta mucho tiempo o el formulario no lleva @csrf. --}}
<x-layouts.app title="La página expiró">
    <div class="py-16 text-center">
        <p class="text-6xl font-bold text-gray-400">419</p>
        <h1 class="mt-4 text-2xl font-semibold">La página expiró</h1>
        <p class="mt-2 text-gray-600">{{ $message }}</p>
        <div class="mt-8">
            <x-button href="/" variant="ghost">Volver al inicio</x-button>
        </div>
    </div>
</x-layouts.app>
