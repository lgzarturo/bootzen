<x-layouts.app :title="__('Crear cuenta')" class="mx-auto max-w-md px-4 py-16">
    <x-card :title="__('Crear cuenta')">
        <x-form :action="route('register.store')">
            <x-input name="nombre" :label="__('Nombre')" autocomplete="name" required autofocus />
            <x-input name="email" type="email" :label="__('Correo')" autocomplete="email" required />
            <x-input name="password" type="password" :label="__('Contraseña')" autocomplete="new-password" minlength="8" required />
            <x-input name="password_confirmation" type="password" :label="__('Repite la contraseña')" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">{{ __('Crear cuenta') }}</x-button>
        </x-form>
        <x-slot:footer>
            <p class="text-sm text-gray-600">{{ __('¿Ya tienes cuenta?') }} <a href="{{ route('login') }}" class="text-blue-600 hover:underline">{{ __('Inicia sesión') }}</a></p>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
