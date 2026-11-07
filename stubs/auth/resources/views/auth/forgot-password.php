<x-layouts.app :title="__('Recuperar contraseña')" class="mx-auto max-w-md px-4 py-16">
    <x-card :title="__('Recuperar contraseña')" :subtitle="__('Te enviamos un enlace para elegir una nueva.')">
        <x-form :action="route('password.email')">
            <x-input name="email" type="email" :label="__('Correo')" autocomplete="email" required autofocus />
            <x-button type="submit" class="w-full">{{ __('Enviar enlace') }}</x-button>
        </x-form>
        <x-slot:footer>
            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">{{ __('Volver a iniciar sesión') }}</a>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
