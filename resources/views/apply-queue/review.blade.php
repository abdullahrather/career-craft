<x-layouts.app :title="$item->title">
    <a href="{{ route('apply-queue') }}" class="link text-sm" wire:navigate>← {{ __('Apply queue') }}</a>

    <div class="mt-3">
        <livewire:apply-queue.review :id="$item->id" />
    </div>
</x-layouts.app>
