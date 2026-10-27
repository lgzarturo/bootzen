{{--
    Barra de navegación. Uso:
    <x-navigation brand="Mi App" :items="[['href' => '/', 'label' => 'Inicio']]" current="/" />
    En pantallas chicas el menú se abre con el componente JS "menu" (public/assets/js/components/menu.js).
--}}
@props(['brand' => 'BootZen', 'items' => [], 'current' => null])
<nav class="border-b border-gray-200 bg-white" data-component="menu">
    <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
        <a href="/" class="text-xl font-bold text-gray-900">{{ $brand }}</a>
        <button type="button" class="text-gray-700 hover:text-blue-600 md:hidden" data-menu-toggle aria-label="Abrir menú" aria-expanded="false">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        <div class="hidden gap-6 md:flex" data-menu-items>
            @foreach ($items as $item)
                <a href="{{ $item['href'] }}" class="{{ class_list([
                    'px-1 py-2 text-sm font-medium transition-colors',
                    'border-b-2 border-blue-600 text-blue-600' => $current === $item['href'],
                    'text-gray-700 hover:text-blue-600' => $current !== $item['href'],
                ]) }}">{{ $item['label'] }}</a>
            @endforeach
        </div>
    </div>
</nav>
