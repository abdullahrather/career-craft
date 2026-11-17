<x-layouts.app :title="$application->title">
    <a href="{{ route('applications') }}" class="link text-sm" wire:navigate>← {{ __('Applications') }}</a>

    <div class="mt-3">
        <livewire:applications.show :id="$application->id" />
    </div>
</x-layouts.app>
