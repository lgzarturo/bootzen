<x-layouts.app title="Iniciar sesión" class="mx-auto max-w-md px-4 py-16">
    <x-card title="Iniciar sesión">
        <x-form :action="route('login.store')">
            <x-input name="email" type="email" label="Correo" autocomplete="email" required autofocus />
            <x-input name="password" type="password" label="Contraseña" autocomplete="current-password" required />
            <div class="flex items-center justify-between">
                <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">¿Olvidaste tu contraseña?</a>
                <x-button type="submit">Entrar</x-button>
            </div>
        </x-form>
        <x-slot:footer>
            <p class="text-sm text-gray-600">¿No tienes cuenta? <a href="{{ route('register') }}" class="text-blue-600 hover:underline">Regístrate</a></p>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
