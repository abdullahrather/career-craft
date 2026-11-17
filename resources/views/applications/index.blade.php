<x-layouts.app :title="__('Applications')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Applications') }}</h1>
        <p class="muted mt-2">{{ __('Every job you are going for, from saved to an offer. Each application keeps the CV and letter you sent.') }}</p>
    </header>

    <div class="mt-6">
        <livewire:applications.board />
    </div>
</x-layouts.app>
