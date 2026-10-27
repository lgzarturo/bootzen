{{--
    Casilla de verificación. Envía 1 si está marcada y 0 si no (campo oculto), así la regla
    boolean siempre recibe un valor. Uso:
    <x-checkbox name="hecha" label="Hecha" :checked="$tarea->hecha" />
--}}
@props(['name', 'label' => null, 'checked' => false])
<div>
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" {{ $attributes->class(['h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500']) }} @if (in_array(old($name, $checked ? '1' : '0'), [true, 1, '1', 'on'], true)) checked @endif>
        {{ $label }}
    </label>
@error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
</div>
