<div>
    <details class="card" wire:ignore.self>
        <summary class="cursor-pointer font-semibold">{{ __('Add a job found elsewhere') }}</summary>
        <form wire:submit="add" class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-wire.input model="title" :label="__('Job title')" />
            <x-wire.input model="company" :label="__('Company')" />
            <x-wire.input model="url" type="url" :label="__('Link to the job')" />
            <x-wire.input model="source" :label="__('Found on')" :hint="__('Such as LinkedIn, StepStone or the company\'s website')" />
            <button type="submit" class="button button-primary self-start" wire:loading.attr="disabled" wire:target="add">{{ __('Add to Saved') }}</button>
        </form>
    </details>

    <p class="muted mt-6 text-sm">{{ __('Drag a card to another column, or change its status in the card.') }}</p>

    <div class="mt-3 grid gap-4 lg:grid-cols-5">
        @foreach ($statuses as $status => $name)
            <section class="flex min-w-0 flex-col gap-3 rounded-2xl bg-stone-100 p-3 dark:bg-stone-900/60" aria-labelledby="column-{{ $status }}">
                <h2 id="column-{{ $status }}" class="px-1 font-semibold">{{ $name }} <span class="muted font-normal">({{ $columns[$status]->count() }})</span></h2>
                <ol wire:sort="move" wire:sort:group="board" wire:sort:group-id="{{ $status }}" class="flex min-h-20 flex-col gap-3">
                    @foreach ($columns[$status] as $application)
                        <li wire:key="application-{{ $application->id }}" wire:sort:item="{{ $application->id }}" class="flex cursor-grab flex-col gap-2 rounded-xl border border-stone-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-stone-800 dark:bg-stone-950">
                            <a href="{{ route('applications.show', ['id' => $application->id]) }}" class="link wrap-break-word" wire:navigate wire:sort:ignore>{{ $application->title }}</a>
                            @if ($application->company !== '')
                                <p class="muted text-sm wrap-break-word">{{ $application->company }}</p>
                            @endif
                            <p class="flex flex-wrap items-center gap-1.5 text-xs">
                                @if ($application->applied_on !== null)
                                    <span class="muted">{{ __('Applied :date', ['date' => $application->applied_on->translatedFormat('j M Y')]) }}</span>
                                @endif
                                @if ($application->cv_path !== null)
                                    <span class="badge">{{ __('CV') }}</span>
                                @endif
                                @if ($application->letter_path !== null)
                                    <span class="badge">{{ __('Letter') }}</span>
                                @endif
                            </p>
                            <select wire:change="setStatus({{ $application->id }}, $event.target.value)" wire:sort:ignore aria-label="{{ __('Status of :title', ['title' => $application->title]) }}" class="rounded-lg border border-stone-300 bg-white px-2 py-1 text-xs dark:border-stone-700 dark:bg-stone-950">
                                @foreach ($statuses as $value => $text)
                                    <option value="{{ $value }}" @selected($value === $status)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
</div>
