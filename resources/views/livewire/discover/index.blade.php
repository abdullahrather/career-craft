@php
    $modes = ['onsite' => __('On site'), 'hybrid' => __('Hybrid'), 'remote' => __('Remote')];
    $periods = ['year' => __('a year'), 'month' => __('a month'), 'hour' => __('an hour')];
@endphp

<div @if ($fetching) wire:poll.3s @endif>
    <form wire:submit="search" class="card grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
        <x-wire.input model="what" :label="__('What')" :hint="__('A job title, skill or company')" />
        <x-wire.input model="where" :label="__('Where')" :hint="__('A city, or leave empty for anywhere')" />
        <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="search">{{ __('Search') }}</button>
        <label class="flex items-center gap-2 text-sm sm:col-span-3">
            <input type="checkbox" wire:model.live="remote">
            {{ __('Include remote jobs from anywhere') }}
        </label>
    </form>

    <div aria-live="polite">
        @if ($fetching)
            <p class="mt-4 flex items-center gap-2 text-sm" role="status">
                <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                {{ __('Fetching new jobs from the job sources… The list updates as they come in.') }}
            </p>
        @endif
        @error('analyse')
            <p class="field-error mt-4 text-sm" role="alert">{{ $message }}</p>
        @enderror
    </div>

    @if ($listings->isEmpty())
        <p class="muted mt-6">{{ $fetching ? __('No jobs found yet.') : __('No jobs found. Search to fetch jobs from the job sources.') }}</p>
    @else
        <ul class="mt-6 flex flex-col gap-4">
            @foreach ($listings as $listing)
                <li wire:key="listing-{{ $listing->id }}" class="card flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="font-semibold wrap-break-word">{{ $listing->title }}</h2>
                        <p class="muted text-sm wrap-break-word">{{ implode(' · ', array_filter([$listing->company, $listing->location])) }}</p>
                        <p class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                            @if ($listing->work_mode !== '')
                                <span class="badge">{{ $modes[$listing->work_mode] ?? $listing->work_mode }}</span>
                            @endif
                            @if ($listing->salary_min !== null || $listing->salary_max !== null)
                                <span class="badge">
                                    {{ implode('–', array_map(fn (int $amount): string => \Illuminate\Support\Number::currency($amount, $listing->salary_currency ?? 'EUR', app()->getLocale(), 0), array_filter([$listing->salary_min, $listing->salary_max], fn (?int $amount): bool => $amount !== null))) }}
                                    {{ $periods[$listing->salary_period] ?? '' }}
                                </span>
                            @endif
                            @if ($listing->posted_at !== null)
                                <span class="muted">{{ $listing->posted_at->translatedFormat('j M Y') }}</span>
                            @endif
                        </p>
                        <p class="muted mt-2 text-xs">
                            {{ __('From') }}
                            @foreach ($listing->sources as $source)
                                <a href="{{ $source->url }}" class="link" target="_blank" rel="noopener noreferrer nofollow">{{ \App\Models\JobListingSource::NAMES[$source->source] ?? $source->source }} ↗</a>@if (! $loop->last), @endif
                            @endforeach
                        </p>
                    </div>
                    <button type="button" wire:click="analyse({{ $listing->id }})" wire:loading.attr="disabled" wire:target="analyse" class="button button-small shrink-0 self-start">{{ __('Analyse') }}</button>
                </li>
            @endforeach
        </ul>

        @if ($listings->hasPages())
            <nav class="mt-6 flex justify-between gap-2" aria-label="{{ __('Pages') }}">
                <button type="button" wire:click="previousPage" @disabled($listings->onFirstPage()) class="button button-small">← {{ __('Newer') }}</button>
                <button type="button" wire:click="nextPage" @disabled(! $listings->hasMorePages()) class="button button-small">{{ __('Older') }} →</button>
            </nav>
        @endif
    @endif
</div>
