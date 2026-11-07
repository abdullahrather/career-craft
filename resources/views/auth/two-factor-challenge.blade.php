<x-layouts.app :title="__('Two-factor login')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Two-factor login') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Enter the code from your authenticator app, or one of your recovery codes.') }}
        </p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <x-field name="code" :label="__('Code from your app')" inputmode="numeric" autocomplete="one-time-code" autofocus />
            <x-field name="recovery_code" :label="__('Or a recovery code')" autocomplete="off" />
            <button type="submit" class="button button-primary">{{ __('Sign in') }}</button>
        </form>
    </div>
</x-layouts.app>
