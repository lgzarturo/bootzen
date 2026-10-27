{{--
    Formulario con CSRF y métodos PUT, PATCH y DELETE simulados. Uso:
    <x-form :action="route('tareas.update', ['id' => $tarea->id])" method="PUT">
        <x-input name="titulo" label="Título" :value="$tarea->titulo" required />
        <x-button type="submit">Guardar</x-button>
    </x-form>
--}}
@props(['action' => '', 'method' => 'POST'])
@if (strtoupper($method) === 'GET')
<form action="{{ $action }}" method="GET" {{ $attributes->merge(['class' => 'space-y-6']) }}>
    {{ $slot }}
</form>
@else
<form action="{{ $action }}" method="POST" {{ $attributes->merge(['class' => 'space-y-6']) }}>
    @csrf
@if (strtoupper($method) !== 'POST')
    @method($method)
@endif
    {{ $slot }}
</form>
@endif
