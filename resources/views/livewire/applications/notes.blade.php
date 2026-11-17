<section class="card flex flex-col gap-4" aria-labelledby="notes">
    <h2 id="notes" class="card-title">{{ __('Notes') }}</h2>
    <form wire:submit="add" class="flex flex-col gap-3">
        <x-wire.textarea model="body" :label="__('Note')" rows="3" />
        <button type="submit" class="button self-start" wire:loading.attr="disabled" wire:target="add">{{ __('Add note') }}</button>
    </form>

    @if ($notes->isNotEmpty())
        <ul class="flex flex-col gap-3">
            @foreach ($notes as $note)
                <li wire:key="note-{{ $note->id }}" class="rounded-xl bg-stone-50 p-3 text-sm dark:bg-stone-950">
                    <div class="flex items-start justify-between gap-3">
                        <p class="muted text-xs">{{ $note->created_at->translatedFormat('j M Y, H:i') }}</p>
                        <button type="button" wire:click="remove({{ $note->id }})" wire:confirm="{{ __('Delete this note?') }}" class="link text-xs" aria-label="{{ __('Delete the note from :date', ['date' => $note->created_at->translatedFormat('j M Y, H:i')]) }}">{{ __('Delete') }}</button>
                    </div>
                    <p class="mt-1 whitespace-pre-line wrap-break-word">{{ $note->body }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
