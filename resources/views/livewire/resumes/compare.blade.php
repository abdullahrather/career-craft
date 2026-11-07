@php
    $label = fn ($version) => __(':name, version :number', ['name' => $version->name, 'number' => $version->number]).' · '.$version->created_at->translatedFormat('j M Y, H:i');
@endphp

<div class="flex flex-col gap-6">
    @if ($versions->count() < 2)
        <p class="card muted">{{ __('Save at least two versions of a CV to compare them.') }}</p>
    @else
        <div class="card grid gap-4 sm:grid-cols-2">
            <label class="field">
                <span>{{ __('Older') }}</span>
                <select wire:model.live="a">
                    <option value="">{{ __('Choose a version') }}</option>
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}">{{ $label($version) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>{{ __('Newer') }}</span>
                <select wire:model.live="b">
                    <option value="">{{ __('Choose a version') }}</option>
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}">{{ $label($version) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($old && $new)
            @php($changes = collect($diff)->where('type', '!=', 'same')->count())
            <section class="card" aria-labelledby="differences">
                <h2 id="differences" class="card-title">{{ trans_choice('{0} No differences|{1} One line changed|[2,*] :count lines changed', $changes) }}</h2>
                <p class="muted mt-1 text-sm">{{ __('The text of each PDF, as a parser reads it. Lines only in the older version are struck through; lines only in the newer one are marked.') }}</p>
                <ol class="mt-4 space-y-0.5 font-mono text-sm">
                    @foreach ($diff as $line)
                        <li @class([
                            'rounded px-2 py-0.5',
                            'muted' => $line['type'] === 'same',
                            'bg-red-50 text-red-800 line-through dark:bg-red-950 dark:text-red-300' => $line['type'] === 'removed',
                            'bg-brand-50 text-brand-900 dark:bg-brand-950 dark:text-brand-200' => $line['type'] === 'added',
                        ])>
                            <span class="sr-only">{{ match ($line['type']) { 'removed' => __('Removed:'), 'added' => __('Added:'), default => '' } }}</span>
                            {{ $line['text'] }}
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @endif
</div>
