<x-layouts.app title="Nueva contraseña" class="mx-auto max-w-md px-4 py-16">
    <x-card title="Elige una contraseña nueva">
        <x-form :action="route('password.update')">
            <input type="hidden" name="token" value="{{ $token }}">
            <x-input name="email" type="email" label="Correo" :value="$email" autocomplete="email" required />
            <x-input name="password" type="password" label="Contraseña nueva" autocomplete="new-password" minlength="8" required autofocus />
            <x-input name="password_confirmation" type="password" label="Repite la contraseña" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">Guardar contraseña</x-button>
        </x-form>
    </x-card>
</x-layouts.app>
