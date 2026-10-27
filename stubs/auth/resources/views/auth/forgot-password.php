<x-layouts.app title="Recuperar contraseña" class="mx-auto max-w-md px-4 py-16">
    <x-card title="Recuperar contraseña" subtitle="Te enviamos un enlace para elegir una nueva.">
        <x-form :action="route('password.email')">
            <x-input name="email" type="email" label="Correo" autocomplete="email" required autofocus />
            <x-button type="submit" class="w-full">Enviar enlace</x-button>
        </x-form>
        <x-slot:footer>
            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Volver a iniciar sesión</a>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
