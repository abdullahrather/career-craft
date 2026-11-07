@props(['id', 'name', 'first' => false, 'last' => false])

{{-- Edit, reorder and delete one entry. Each button names the entry for screen readers. --}}
<div class="flex shrink-0 items-center gap-1">
    <button type="button" wire:click="move({{ $id }}, -1)" @disabled($first) class="button button-small size-7 p-0" aria-label="{{ __('Move :name up', ['name' => $name]) }}">↑</button>
    <button type="button" wire:click="move({{ $id }}, 1)" @disabled($last) class="button button-small size-7 p-0" aria-label="{{ __('Move :name down', ['name' => $name]) }}">↓</button>
    <button type="button" wire:click="edit({{ $id }})" class="button button-small" aria-label="{{ __('Edit :name', ['name' => $name]) }}">{{ __('Edit') }}</button>
    <button type="button" wire:click="delete({{ $id }})" wire:confirm="{{ __('Delete :name?', ['name' => $name]) }}" class="button button-small button-danger" aria-label="{{ __('Delete :name', ['name' => $name]) }}">{{ __('Delete') }}</button>
</div>
