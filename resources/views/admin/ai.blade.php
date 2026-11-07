<x-layouts.app :title="__('AI settings')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('AI settings') }}</h1>
        <p class="muted mt-2">{{ __('Which AI the platform uses, how much each person may use per day, and what it costs. API keys stay in .env and never appear here.') }}</p>
    </header>

    <div class="mt-8">
        <livewire:admin.ai-settings />
    </div>
</x-layouts.app>
