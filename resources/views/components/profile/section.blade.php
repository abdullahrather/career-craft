@props(['id', 'title', 'description' => null, 'empty' => false, 'open' => false, 'addLabel' => null])

<section id="{{ $id }}" class="card scroll-mt-6" aria-labelledby="{{ $id }}-title">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 id="{{ $id }}-title" class="card-title">{{ $title }}</h2>
            @if ($description)
                <p class="muted mt-1 text-sm">{{ $description }}</p>
            @endif
        </div>
        @if ($addLabel && ! $open)
            <button type="button" wire:click="add" class="button button-small shrink-0">+ {{ $addLabel }}</button>
        @endif
    </div>

    @if ($empty && ! $open)
        <p class="mt-4 rounded-xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-stone-500 dark:border-stone-700 dark:text-stone-400">
            {{ __('Nothing here yet.') }}
        </p>
    @endif

    {{ $slot }}
</section>
