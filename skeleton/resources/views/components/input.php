{{--
    Campo de formulario con etiqueta y error de validación. Uso:
    <x-input name="email" type="email" label="Correo" :value="$email" required />
    El error se toma de @error(name) cuando la validación (fase 4) lo comparta.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null])
<div>
@if ($label)
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-gray-700">
        {{ $label }}@if ($attributes->has('required'))<span class="ml-1 text-red-500">*</span>@endif
    </label>
@endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" {{ $attributes->class([
        'w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2',
        'border-gray-300 focus:ring-blue-500' => empty($errors[$name]),
        'border-red-300 focus:ring-red-500' => ! empty($errors[$name]),
    ]) }}>
@error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
</div>
