<x-layouts.app :title="__('Find jobs')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Find jobs') }}</h1>
        <p class="muted mt-2">{{ __('Jobs from the Bundesagentur für Arbeit, Arbeitnow, the companies you follow and, with a free key, Adzuna. The same job from two sources is shown once. Analyse one to see your match, tailor a CV and write a letter.') }}</p>
    </header>

    <div class="mt-6">
        <livewire:discover.index />
    </div>
</x-layouts.app>
