{{--
    Alerta. Uso: <x-alert type="success" title="Listo" dismissible>Se guardó la tarea.</x-alert>
    type: info, success, warning, error
--}}
@props(['type' => 'info', 'title' => null, 'dismissible' => false])
<div {{ $attributes->class([
    'rounded-lg border p-4',
    'border-blue-200 bg-blue-50 text-blue-800' => $type === 'info',
    'border-green-200 bg-green-50 text-green-800' => $type === 'success',
    'border-yellow-200 bg-yellow-50 text-yellow-800' => $type === 'warning',
    'border-red-200 bg-red-50 text-red-800' => $type === 'error',
]) }} role="{{ $type === 'error' ? 'alert' : 'status' }}" data-dismissible>
    <div class="flex gap-3">
        <div class="flex-1">
@if ($title)
            <h4 class="mb-1 font-medium">{{ $title }}</h4>
@endif
            <div>{{ $slot }}</div>
        </div>
@if ($dismissible)
        <button type="button" class="text-current opacity-60 hover:opacity-100" data-dismiss aria-label="Cerrar">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        </button>
@endif
    </div>
</div>
