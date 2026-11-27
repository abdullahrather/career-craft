<x-layouts.app :title="__('Practise: :title', ['title' => $application->title])">
    <a href="{{ route('applications.show', ['id' => $application->id]) }}" class="link text-sm" wire:navigate>← {{ $application->title }}</a>

    <div class="mt-3">
        <livewire:applications.practice :id="$application->id" />
    </div>
</x-layouts.app>
