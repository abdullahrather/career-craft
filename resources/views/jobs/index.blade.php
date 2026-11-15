<x-layouts.app :title="__('Jobs')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Jobs') }}</h1>
        <p class="muted mt-2">{{ __('Paste a job post or its web address. CareerCraft reads what the role asks for and shows how your profile matches it.') }}</p>
    </header>

    <div class="mt-8">
        <livewire:jobs.index />
    </div>
</x-layouts.app>
