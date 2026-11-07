{{-- Los textos con <code> usan {!! !!}: la traducción la escribes tú y los marcadores son fijos. --}}
<x-layouts.app :title="__('Inicio')">
    <section class="py-10 text-center">
        <h1 class="text-4xl font-bold tracking-tight">{{ __('Hola desde :app', ['app' => config('app.name')]) }}</h1>
        <p class="mt-3 text-lg text-gray-600">{!! __('Proyecto creado con BootZen. Empieza por :archivo.', ['archivo' => '<code class="rounded bg-gray-100 px-1">routes/web.php</code>']) !!}</p>
    </section>

    <div class="grid gap-6 md:grid-cols-3">
        <x-card :title="__('Rutas')" subtitle="routes/web.php">
            {!! __('Define páginas con :get o un CRUD completo con :resource.', ['get' => '<code>$router->get()</code>', 'resource' => '<code>$router->resource()</code>']) !!}
        </x-card>
        <x-card :title="__('Vistas')" subtitle="resources/views">
            {!! __('Plantillas :php con componentes :card, slots y directivas.', ['php' => '<code>.php</code>', 'card' => '<code>&lt;x-card&gt;</code>']) !!}
        </x-card>
        <x-card :title="__('Interactividad')" subtitle="public/assets/js">
            {!! __('Componentes JS nativos con :atributo, sin bundler.', ['atributo' => '<code>data-component</code>']) !!}
            <x-slot:footer>
                <div data-component="counter" data-props='{"start": 0}' class="flex items-center gap-3">
                    <x-button size="sm" data-counter-increment>{{ __('Sumar') }}</x-button>
                    <span>{{ __('Clics:') }} <strong data-counter-value>0</strong></span>
                </div>
            </x-slot:footer>
        </x-card>
    </div>

    <x-alert type="info" :title="__('Siguiente paso')" class="mt-8">
        {!! __('Lee :archivo: ahí están las convenciones del proyecto para personas y agentes de IA.', ['archivo' => '<code>AGENTS.md</code>']) !!}
    </x-alert>
</x-layouts.app>
