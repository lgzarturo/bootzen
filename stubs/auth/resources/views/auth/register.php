<x-layouts.app title="Crear cuenta" class="mx-auto max-w-md px-4 py-16">
    <x-card title="Crear cuenta">
        <x-form :action="route('register.store')">
            <x-input name="nombre" label="Nombre" autocomplete="name" required autofocus />
            <x-input name="email" type="email" label="Correo" autocomplete="email" required />
            <x-input name="password" type="password" label="Contraseña" autocomplete="new-password" minlength="8" required />
            <x-input name="password_confirmation" type="password" label="Repite la contraseña" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">Crear cuenta</x-button>
        </x-form>
        <x-slot:footer>
            <p class="text-sm text-gray-600">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Inicia sesión</a></p>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
