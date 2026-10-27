<x-layouts.app title="Inicio">
    <section class="py-10 text-center">
        <h1 class="text-4xl font-bold tracking-tight">Hola desde {{ config('app.name') }}</h1>
        <p class="mt-3 text-lg text-gray-600">Proyecto creado con BootZen. Empieza por <code class="rounded bg-gray-100 px-1">routes/web.php</code>.</p>
    </section>

    <div class="grid gap-6 md:grid-cols-3">
        <x-card title="Rutas" subtitle="routes/web.php">
            Define páginas con <code>$router->get()</code> o un CRUD completo con <code>$router->resource()</code>.
        </x-card>
        <x-card title="Vistas" subtitle="resources/views">
            Plantillas <code>.php</code> con componentes <code>&lt;x-card&gt;</code>, slots y directivas.
        </x-card>
        <x-card title="Interactividad" subtitle="public/assets/js">
            Componentes JS nativos con <code>data-component</code>, sin bundler.
            <x-slot:footer>
                <div data-component="counter" data-props='{"start": 0}' class="flex items-center gap-3">
                    <x-button size="sm" data-counter-increment>Sumar</x-button>
                    <span>Clics: <strong data-counter-value>0</strong></span>
                </div>
            </x-slot:footer>
        </x-card>
    </div>

    <x-alert type="info" title="Siguiente paso" class="mt-8">
        Lee <code>AGENTS.md</code>: ahí están las convenciones del proyecto para personas y agentes de IA.
    </x-alert>
</x-layouts.app>
