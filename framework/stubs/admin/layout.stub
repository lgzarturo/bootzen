{{--
    Layout del panel de administración (make:admin). El menú de la izquierda sale de config/admin.php
    (menu). Uso: <x-layouts.admin :title="__('Productos')"> contenido </x-layouts.admin>
--}}
@props(['title' => null])
<!doctype html>
<html lang="{{ locale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ ($title ? $title . ' · ' : '') . __('Administración') . ' · ' . config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script type="module" src="{{ asset('assets/js/app.js') }}"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="flex h-14 items-center justify-between px-4">
            <a href="{{ route('home') }}" class="font-bold">{{ config('app.name') }} <span class="font-normal text-gray-500">· {{ __('Administración') }}</span></a>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-600">{{ auth()->user()?->getAttribute('nombre') }}</span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <x-button type="submit" variant="ghost" size="sm">{{ __('Salir') }}</x-button>
                </form>
            </div>
        </div>
    </header>
    <div class="md:flex">
        <nav class="border-b border-gray-200 bg-white p-4 md:min-h-[calc(100vh-3.5rem)] md:w-56 md:border-r md:border-b-0" aria-label="{{ __('Administración') }}">
            <ul class="flex flex-wrap gap-2 md:flex-col md:gap-1">
                @foreach ((array) config('admin.menu', []) as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" class="{{ class_list([
                            'block rounded-lg px-3 py-2 text-sm font-medium',
                            'bg-blue-50 text-blue-700' => str_starts_with(current_path(), (string) parse_url(route($item['route']), PHP_URL_PATH)),
                            'text-gray-700 hover:bg-gray-100' => ! str_starts_with(current_path(), (string) parse_url(route($item['route']), PHP_URL_PATH)),
                        ]) }}">{{ __($item['label']) }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <main class="flex-1 px-4 py-8 md:px-8">
@if (session('status'))
            <x-alert type="success" class="mb-6" dismissible>{{ session('status') }}</x-alert>
@endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
