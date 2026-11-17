<x-layouts.app :title="__('Apply queue')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Apply queue') }}</h1>
        <p class="muted mt-2">{{ __('Queue jobs from Find jobs, and each one is prepared for you: the post is analysed, your CV tailored and a letter written. You review and approve each one; nothing goes to an employer without you.') }}</p>
    </header>

    <div class="mt-6">
        <livewire:apply-queue.index />
    </div>
</x-layouts.app>
