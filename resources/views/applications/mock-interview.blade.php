<x-layouts.app :title="__('Mock interview: :title', ['title' => $application->title])">
    <a href="{{ route('applications.practice', ['id' => $application->id]) }}" class="link text-sm" wire:navigate>← {{ __('Practise for the interview') }}</a>

    <div class="mt-3">
        <livewire:applications.mock-interview :id="$application->id" />
    </div>
</x-layouts.app>
