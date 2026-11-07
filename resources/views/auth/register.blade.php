<x-layouts.app :title="__('Create account')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Create your account') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Free, and it takes a minute.') }}</p>

        <x-social-buttons />

        <form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <x-field name="name" :label="__('Name')" autocomplete="name" required autofocus />
            <x-field name="email" type="email" :label="__('Email')" autocomplete="email" required />
            <x-field name="password" type="password" :label="__('Password (at least 8 characters)')" autocomplete="new-password" required />
            <x-field name="password_confirmation" type="password" :label="__('Password again')" autocomplete="new-password" required />

            <button type="submit" class="button button-primary">{{ __('Create account') }}</button>
        </form>

        <p class="mt-6 text-sm">
            <a href="{{ route('login') }}" class="link">{{ __('Already have an account? Sign in') }}</a>
        </p>
    </div>
</x-layouts.app>
