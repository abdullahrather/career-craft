<x-layouts.app :title="__('Import your CV')">
    <a href="{{ route('profile') }}" class="link text-sm">← {{ __('Career profile') }}</a>
    <header class="mt-3 max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Import your CV') }}</h1>
        <p class="muted mt-2">{{ __('Start from the CV you already have, or from your LinkedIn profile. You check everything that was found before it goes into your profile.') }}</p>
    </header>

    <div class="mt-8">
        <livewire:profile.import />
    </div>
</x-layouts.app>
