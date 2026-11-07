<x-layouts.app :title="__('CVs')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('CVs') }}</h1>
        <p class="muted mt-2">{{ __('Every CV is built from your career profile, so a change there shows up in all of them. Make one per kind of role, in English or German.') }}</p>
    </header>

    <div class="mt-8">
        <livewire:resumes.index />
    </div>
</x-layouts.app>
