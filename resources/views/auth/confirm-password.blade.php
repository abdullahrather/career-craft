<x-layouts.app :title="__('Confirm your password')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Confirm your password') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('This is a security setting, so please enter your password first.') }}</p>

        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <x-field name="password" type="password" :label="__('Password')" autocomplete="current-password" required autofocus />
            <button type="submit" class="button button-primary">{{ __('Confirm') }}</button>
        </form>
    </div>
</x-layouts.app>
