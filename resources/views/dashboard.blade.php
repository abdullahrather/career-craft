<x-layouts.app :title="__('Dashboard')">
    <h1 class="text-3xl font-semibold">{{ __('Welcome, :name', ['name' => auth()->user()?->name]) }}</h1>

    <section class="card mt-8 max-w-2xl">
        <h2 class="card-title">{{ __('Your career profile comes next') }}</h2>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Your account is ready. The profile, CV builder and job tools are being built, and they will appear here.') }}
        </p>
        <a href="{{ route('settings') }}" class="link mt-4 inline-block">{{ __('Review your settings and turn on two-factor login') }}</a>
    </section>
</x-layouts.app>
