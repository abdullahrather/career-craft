<x-layouts.app :title="__('Reset your password')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Reset your password') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __("We'll email you a link to choose a new one.") }}</p>

        @if (session('status'))
            <p class="notice mt-4" role="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <x-field name="email" type="email" :label="__('Email')" autocomplete="email" required autofocus />
            <button type="submit" class="button button-primary">{{ __('Send the link') }}</button>
        </form>

        <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="link">{{ __('Back to sign in') }}</a></p>
    </div>
</x-layouts.app>
