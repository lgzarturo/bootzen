<x-layouts.app :title="__('Verifica tu correo')" class="mx-auto max-w-md px-4 py-16">
    <x-card :title="__('Verifica tu correo')">
        <p class="text-sm text-gray-700">{{ __('Te enviamos un enlace a :email. Ábrelo para activar tu cuenta.', ['email' => $user->email]) }}</p>
        <p class="mt-2 text-sm text-gray-500">{{ __('Si no lo ves, revisa la carpeta de spam o pide otro.') }}</p>
        <x-slot:footer>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-form :action="route('verification.send')">
                    <x-button type="submit">{{ __('Reenviar enlace') }}</x-button>
                </x-form>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <x-button type="submit" variant="ghost">{{ __('Salir') }}</x-button>
                </form>
            </div>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
