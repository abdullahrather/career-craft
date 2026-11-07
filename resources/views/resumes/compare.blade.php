<x-layouts.app :title="__('Compare versions')">
    <a href="{{ route('resumes') }}" class="link text-sm">← {{ __('CVs') }}</a>
    <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('Compare versions') }}</h1>

    <div class="mt-6">
        <livewire:resumes.compare />
    </div>
</x-layouts.app>
