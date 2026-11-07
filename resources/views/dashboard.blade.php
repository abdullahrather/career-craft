@php($completeness = auth()->user()->careerProfile()->completeness())

<x-layouts.app :title="__('Dashboard')">
    <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Welcome, :name', ['name' => auth()->user()?->name]) }}</h1>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="card-title">{{ __('Your career profile') }}</h2>
            <p class="muted mt-2">
                @if ($completeness['score'] === 100)
                    {{ __('Complete. Every CV you make starts from a strong profile.') }}
                @else
                    {{ __('Your profile is :score% complete.', ['score' => $completeness['score']]) }}
                @endif
            </p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800" role="progressbar" aria-valuenow="{{ $completeness['score'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Profile strength') }}">
                <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $completeness['score'] }}%"></div>
            </div>
            @if ($completeness['next'] !== [])
                <p class="mt-4 text-sm">{{ $completeness['next'][0]['text'] }}</p>
            @endif
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('profile') }}" class="button button-primary">{{ __('Open your profile') }}</a>
                @if ($completeness['score'] < 100)
                    <a href="{{ route('profile.import') }}" class="button">{{ __('Import a CV or LinkedIn') }}</a>
                @endif
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">{{ __('Your CVs') }}</h2>
            <p class="muted mt-2">{{ __('Turn your profile into a CV in English or German, with a template that suits the role.') }}</p>
            <a href="{{ route('resumes') }}" class="button mt-5">{{ __('Go to your CVs') }}</a>
        </section>

        <section class="card">
            <h2 class="card-title">{{ __('Keep your account safe') }}</h2>
            <p class="muted mt-2">{{ __('Turn on two-factor login, so a stolen password alone is not enough to get in.') }}</p>
            <a href="{{ route('settings') }}" class="button mt-5">{{ __('Go to settings') }}</a>
        </section>
    </div>
</x-layouts.app>
