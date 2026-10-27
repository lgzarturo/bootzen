{{--
    Layout base. Muestra session('status') como alerta (redirect(...)->with('status', '...')). Uso:
    <x-layouts.app title="Tareas" description="...">
        contenido
        <x-slot:head> <link ...> </x-slot:head>       (opcional)
        <x-slot:scripts> <script ...> </x-slot:scripts> (opcional)
    </x-layouts.app>
--}}
@props(['title' => null, 'description' => null])
<!doctype html>
<html lang="{{ config('app.locale', 'es') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · ' . config('app.name') : config('app.name') }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script type="module" src="{{ asset('assets/js/app.js') }}"></script>
@if (isset($head))
    {{ $head }}
@endif
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <x-navigation :brand="config('app.name')" :items="[['href' => '/', 'label' => 'Inicio']]" />
    <main {{ $attributes->merge(['class' => 'mx-auto max-w-5xl px-4 py-10']) }}>
@if (session('status'))
        <x-alert type="success" class="mb-6" dismissible>{{ session('status') }}</x-alert>
@endif
        {{ $slot }}
    </main>
@if (isset($scripts))
    {{ $scripts }}
@endif
</body>
</html>
