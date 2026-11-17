@php
    $stateNames = ['preparing' => __('Preparing'), 'ready' => __('Ready to review'), 'failed' => __('Stopped')];
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start" @if (in_array('preparing', $states, true)) wire:poll.5s @endif>
    <div class="flex min-w-0 flex-col gap-6">
        <section class="card" aria-labelledby="queue">
            <h2 id="queue" class="card-title">{{ __('In the queue') }}</h2>
            @if ($open->isEmpty())
                <p class="muted mt-2 text-sm">{{ __('Nothing queued. On Find jobs, press "Queue" on a job: it is analysed, and gets a tailored CV and a letter for you to review.') }}</p>
                <a href="{{ route('discover') }}" class="button mt-4" wire:navigate>{{ __('Find jobs') }}</a>
            @else
                <ul class="mt-4 flex flex-col gap-3">
                    @foreach ($open as $item)
                        <li wire:key="item-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 p-3 dark:border-stone-800">
                            <div class="min-w-0">
                                <a href="{{ route('apply-queue.review', ['id' => $item->id]) }}" class="link wrap-break-word" wire:navigate>{{ $item->title }}</a>
                                @if ($item->company !== '')
                                    <p class="muted text-sm wrap-break-word">{{ $item->company }}</p>
                                @endif
                            </div>
                            <span @class(['badge', 'badge-brand' => $states[$item->id] === 'ready'])>
                                @if ($states[$item->id] === 'preparing')
                                    <span class="mr-1.5 size-3 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                                @endif
                                {{ $stateNames[$states[$item->id]] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="muted mt-4 text-xs">{{ __(':count of :max queued today.', ['count' => $queuedToday, 'max' => \App\Models\ApplyQueueItem::DAILY]) }}</p>
        </section>

        <section class="card" aria-labelledby="log">
            <h2 id="log" class="card-title">{{ __('Log') }}</h2>
            @if ($history->isEmpty())
                <p class="muted mt-2 text-sm">{{ __('Every job you approve or skip is listed here.') }}</p>
            @else
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @foreach ($history as $item)
                        <li wire:key="log-{{ $item->id }}" class="flex flex-wrap items-baseline justify-between gap-x-3">
                            <span class="min-w-0 wrap-break-word">
                                @if ($item->application_id !== null)
                                    <a href="{{ route('applications.show', ['id' => $item->application_id]) }}" class="link" wire:navigate>{{ $item->title }}</a>
                                @else
                                    {{ $item->title }}
                                @endif
                                @if ($item->company !== '')
                                    <span class="muted">· {{ $item->company }}</span>
                                @endif
                            </span>
                            <span class="muted text-xs">
                                {{ $item->approved_at !== null ? __('Approved :date', ['date' => $item->approved_at->translatedFormat('j M Y, H:i')]) : __('Skipped :date', ['date' => $item->skipped_at?->translatedFormat('j M Y, H:i')]) }}
                                · {{ __('queued :date', ['date' => $item->created_at->translatedFormat('j M Y, H:i')]) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <form wire:submit="saveSettings" class="card flex flex-col gap-4">
        <h2 class="card-title">{{ __('How it prepares') }}</h2>
        <label class="field" for="resumeId">
            <span>{{ __('CV to start from') }}</span>
            <select id="resumeId" wire:model="resumeId">
                <option value="">{{ __('Choose a CV') }}</option>
                @foreach ($resumes as $resume)
                    <option value="{{ $resume->id }}">{{ $resume->name }}</option>
                @endforeach
            </select>
            <span class="field-hint">{{ __('Each job gets a copy of it, with the AI\'s suggestions for you to accept or not.') }}</span>
            @error('resumeId')
                <span class="field-error" role="alert">{{ $message }}</span>
            @enderror
        </label>
        <label class="field" for="tone">
            <span>{{ __('Letter tone') }}</span>
            <select id="tone" wire:model="tone">
                <option value="formal">{{ __('Formal') }}</option>
                <option value="warm">{{ __('Warm') }}</option>
                <option value="confident">{{ __('Confident') }}</option>
            </select>
            <span class="field-hint">{{ __('Letters are written in the language of the job post.') }}</span>
        </label>
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="saveSettings">{{ __('Save') }}</button>
            @if (session('settings-saved'))
                <p class="text-sm" role="status">{{ session('settings-saved') }}</p>
            @endif
        </div>
    </form>
</div>
