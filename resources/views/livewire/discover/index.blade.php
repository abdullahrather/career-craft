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

    <div class="mt-4">
        <livewire:discover.companies />
    </div>

    <details class="card mt-4" wire:ignore.self>
        <summary class="cursor-pointer font-semibold">{{ __('Saved searches') }} ({{ $savedSearches->count() }})</summary>
        <p class="muted mt-2 text-sm">{{ __('Save the search on this page, and its new jobs are emailed to you, best match first.') }}</p>
        <form wire:submit="saveSearch" class="mt-4 flex flex-wrap items-end gap-4">
            <label class="field" for="frequency">
                <span>{{ __('Email me') }}</span>
                <select id="frequency" wire:model="frequency">
                    <option value="daily">{{ __('Daily') }}</option>
                    <option value="weekly">{{ __('Weekly') }}</option>
                </select>
            </label>
            <button type="submit" class="button" wire:loading.attr="disabled" wire:target="saveSearch">{{ __('Save this search') }}</button>
        </form>
        <div aria-live="polite">
            @foreach (['frequency', 'mode', 'company'] as $field)
                @error($field)
                    <p class="field-error mt-2 text-sm" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            @if (session('saved'))
                <p class="mt-2 text-sm" role="status">{{ session('saved') }}</p>
            @endif
        </div>

        @if ($savedSearches->isNotEmpty())
            <ul class="mt-4 flex flex-col gap-2">
                @foreach ($savedSearches as $saved)
                    @php($label = $saved->filters()->label())
                    <li wire:key="saved-{{ $saved->id }}" class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" wire:click="openSearch({{ $saved->id }})" class="link min-w-0 text-left wrap-break-word">{{ $label }}</button>
                        <span class="flex shrink-0 items-center gap-2">
                            <select wire:change="setFrequency({{ $saved->id }}, $event.target.value)" aria-label="{{ __('Email for :search', ['search' => $label]) }}">
                                <option value="daily" @selected($saved->frequency === 'daily')>{{ __('Daily') }}</option>
                                <option value="weekly" @selected($saved->frequency === 'weekly')>{{ __('Weekly') }}</option>
                            </select>
                            <button type="button" wire:click="deleteSearch({{ $saved->id }})" class="button button-small" aria-label="{{ __('Delete :search', ['search' => $label]) }}">{{ __('Delete') }}</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </details>

    @if ($companyFacts !== null)
        <section class="card mt-4" aria-labelledby="company-name">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="company-name" class="text-lg font-semibold wrap-break-word">{{ $company }}</h2>
                    <p class="muted text-sm">{{ trans_choice('{0} No open jobs|{1} One open job|[2,*] :count open jobs', $companyFacts['jobs']) }}</p>
                </div>
                <button type="button" wire:click="$set('company', '')" class="button button-small">{{ __('All companies') }}</button>
            </div>
            @if ($companyFacts['places'] !== [])
                <p class="mt-2 text-sm wrap-break-word">{{ __('Where: :places', ['places' => implode(', ', $companyFacts['places'])]) }}</p>
            @endif
            @if ($companyFacts['modes'] !== [])
                <p class="mt-1 text-sm">{{ __('Work mode') }}: {{ implode(' · ', array_filter(array_map(fn (string $mode, string $text): string => isset($companyFacts['modes'][$mode]) ? "{$text} {$companyFacts['modes'][$mode]}" : '', array_keys($modes), $modes))) }}</p>
            @endif
            <p class="muted mt-2 text-sm">{{ $companyFacts['followed'] ? __('You follow its job board, so its jobs are fetched again every day.') : __('Save this search to get an email when the company lists new jobs.') }}</p>
        </section>
    @endif

    <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
        <label class="field" for="sort">
            <span>{{ __('Order') }}</span>
            <select id="sort" wire:model.live="sort">
                <option value="match">{{ __('Best match for your profile') }}</option>
                <option value="newest">{{ __('Newest first') }}</option>
            </select>
        </label>
        <label class="field" for="mode">
            <span>{{ __('Work mode') }}</span>
            <select id="mode" wire:model.live="mode">
                <option value="">{{ __('Any') }}</option>
                @foreach ($modes as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm sm:pb-2.5">
            <input type="checkbox" wire:model.live="salary">
            {{ __('Only with a salary') }}
        </label>
    </div>

    <div aria-live="polite">
        @if ($sort === 'match' && ! $canRank)
            <p class="muted mt-4 text-sm">{{ __('Add skills or job titles to your career profile to see the jobs that fit you best first. Until then, the newest come first.') }} <a href="{{ route('profile') }}" class="link" wire:navigate>{{ __('Career profile') }}</a></p>
        @endif
        @if ($hiddenTitle !== null)
            <p class="mt-4 text-sm" role="status">
                {{ __('Hidden: :title. Jobs like it will rank lower.', ['title' => $hiddenTitle]) }}
                <button type="button" wire:click="undo" class="link">{{ __('Undo') }}</button>
            </p>
        @endif
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
                        <p class="muted text-sm wrap-break-word">
                            @if ($listing->company !== '')
                                <a href="{{ route('discover', ['company' => $listing->company]) }}" class="link" wire:navigate>{{ $listing->company }}</a>@if ($listing->location !== '') · @endif
                            @endif
                            {{ $listing->location }}
                        </p>
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
                        @if (($yourSkills[$listing->id] ?? []) !== [])
                            <p class="mt-2 text-sm">{{ __('Your skills: :skills', ['skills' => implode(', ', $yourSkills[$listing->id])]) }}</p>
                        @endif
                        <p class="muted mt-2 text-xs">
                            {{ __('From') }}
                            @foreach ($listing->sources as $source)
                                <a href="{{ $source->url }}" class="link" target="_blank" rel="noopener noreferrer nofollow">{{ \App\Models\JobListingSource::NAMES[$source->source] ?? $source->source }} ↗</a>@if (! $loop->last), @endif
                            @endforeach
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 self-start">
                        @if (in_array($listing->id, $tracked, true))
                            <a href="{{ route('applications') }}" class="badge badge-brand" wire:navigate>{{ __('Tracked') }} ✓</a>
                        @else
                            <button type="button" wire:click="track({{ $listing->id }})" class="button button-small" aria-label="{{ __('Track :title', ['title' => $listing->title]) }}">{{ __('Track') }}</button>
                        @endif
                        <button type="button" wire:click="analyse({{ $listing->id }})" wire:loading.attr="disabled" wire:target="analyse" class="button button-small">{{ __('Analyse') }}</button>
                        <button type="button" wire:click="dismiss({{ $listing->id }})" class="button button-small" aria-label="{{ __('Not interested in :title', ['title' => $listing->title]) }}">{{ __('Not interested') }}</button>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($listings->hasPages())
            <nav class="mt-6 flex justify-between gap-2" aria-label="{{ __('Pages') }}">
                <button type="button" wire:click="previousPage" @disabled($listings->onFirstPage()) class="button button-small">← {{ $ranked ? __('Previous') : __('Newer') }}</button>
                <button type="button" wire:click="nextPage" @disabled(! $listings->hasMorePages()) class="button button-small">{{ $ranked ? __('Next') : __('Older') }} →</button>
            </nav>
        @endif
    @endif
</div>
