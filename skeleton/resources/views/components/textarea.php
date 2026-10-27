{{--
    Área de texto con etiqueta y error de validación. Uso:
    <x-textarea name="notas" label="Notas" :value="$tarea->notas" rows="5" />
    Tras un error de validación muestra el mensaje y el texto enviado (old).
--}}
@props(['name', 'label' => null, 'value' => null, 'rows' => 4])
<div>
@if ($label)
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-gray-700">
        {{ $label }}@if ($attributes->has('required'))<span class="ml-1 text-red-500">*</span>@endif
    </label>
@endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class([
        'w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2',
        'border-gray-300 focus:ring-blue-500' => empty($errors[$name]),
        'border-red-300 focus:ring-red-500' => ! empty($errors[$name]),
    ]) }}>{{ old($name, $value) }}</textarea>
@error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
</div>
