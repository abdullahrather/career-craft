<x-layouts.guest :title="__('Sign in')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Sign in') }}</h1>

        @if (session('status'))
            <p class="notice mt-4" role="status">{{ session('status') }}</p>
        @endif

        <x-social-buttons />

        <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <x-field name="email" type="email" :label="__('Email')" autocomplete="email" required autofocus />
            <x-field name="password" type="password" :label="__('Password')" autocomplete="current-password" required />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="rounded border-zinc-300">
                {{ __('Keep me signed in') }}
            </label>

            <button type="submit" class="button button-primary">{{ __('Sign in') }}</button>
        </form>

        <div class="mt-6 flex justify-between text-sm">
            <a href="{{ route('password.request') }}" class="link">{{ __('Forgot your password?') }}</a>
            <a href="{{ route('register') }}" class="link">{{ __('Create account') }}</a>
        </div>
    </div>
</x-layouts.guest>
