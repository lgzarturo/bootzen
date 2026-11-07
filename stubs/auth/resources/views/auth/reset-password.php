<x-layouts.app :title="__('Nueva contraseña')" class="mx-auto max-w-md px-4 py-16">
    <x-card :title="__('Elige una contraseña nueva')">
        <x-form :action="route('password.update')">
            <input type="hidden" name="token" value="{{ $token }}">
            <x-input name="email" type="email" :label="__('Correo')" :value="$email" autocomplete="email" required />
            <x-input name="password" type="password" :label="__('Contraseña nueva')" autocomplete="new-password" minlength="8" required autofocus />
            <x-input name="password_confirmation" type="password" :label="__('Repite la contraseña')" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">{{ __('Guardar contraseña') }}</x-button>
        </x-form>
    </x-card>
</x-layouts.app>
