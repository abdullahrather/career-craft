@php
    $waiting = $changes->where('status', \App\Models\TailoredChange::WAITING)->count();
    $ready = $changes->where('status', \App\Models\TailoredChange::READY);
    $accepted = $changes->where('status', \App\Models\TailoredChange::ACCEPTED);
    $failed = $changes->where('status', \App\Models\TailoredChange::FAILED);
    $label = fn (\App\Models\TailoredChange $change): string => $change->experience === null
        ? __('Summary')
        : implode(' · ', array_filter([$change->experience->title, $change->experience->employer]));
@endphp

<section class="card mb-6" aria-labelledby="tailoring-title" @if ($waiting > 0) wire:poll.3s @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 id="tailoring-title" class="card-title">{{ __('Tailored to a job') }}</h2>
            @if ($post !== null)
                <a href="{{ route('jobs.show', ['id' => $post->id]) }}" class="link text-sm wrap-break-word">{{ $post->title !== '' ? $post->title : __('Job post') }}{{ $post->company !== '' ? ' · '.$post->company : '' }}</a>
            @else
                <p class="muted text-sm">{{ __('The job post was deleted. Accepted changes stay on this CV.') }}</p>
            @endif
        </div>
        @if ($before !== null && $before['score'] !== null && $after['score'] !== null)
            <div class="flex items-center gap-3">
                <div class="text-center">
                    <x-jobs.score :score="$before['score']" size="small" />
                    <p class="muted mt-1 text-xs">{{ __('Before') }}</p>
                </div>
                <span aria-hidden="true">→</span>
                <div class="text-center">
                    <x-jobs.score :score="$after['score']" size="small" />
                    <p class="muted mt-1 text-xs">{{ __('Now') }}</p>
                </div>
            </div>
        @endif
    </div>
    <p class="muted mt-3 text-sm">{{ __('The score counts the skills this CV shows. Skills the job asks for come first in your skills and bullet points. Accepted changes show on this CV only; your profile stays as it is.') }}</p>

    @error('tailoring')
        <p class="field-error mt-3 text-sm" role="alert">{{ $message }}</p>
    @enderror

    <div aria-live="polite">
        @if ($waiting > 0)
            <p class="mt-4 flex items-center gap-2 text-sm" role="status">
                <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                {{ trans_choice('{1} Writing and checking one more suggestion…|[2,*] Writing and checking :count more suggestions…', $waiting) }}
            </p>
        @elseif ($changes->isEmpty())
            <p class="notice mt-4">{{ __('This CV shows no summary or bullet points to reword.') }}</p>
        @elseif ($ready->isEmpty() && $accepted->isEmpty() && $failed->isEmpty())
            <p class="notice mt-4">{{ __('No changes suggested: your CV already fits this job.') }}</p>
        @endif

        @if ($failed->isNotEmpty())
            <p class="alert mt-4">
                {{ $failed->contains('error', 'limit') ? __('You have used today\'s AI allowance. It starts again at midnight.') : __('Some suggestions could not be written. Tailor the CV again later.') }}
            </p>
        @endif

        @if ($ready->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold">{{ trans_choice('{1} One suggestion|[2,*] :count suggestions', $ready->count()) }}</p>
                @if ($ready->count() > 1)
                    <button type="button" wire:click="acceptAll" class="button button-small button-primary">{{ __('Accept all') }}</button>
                @endif
            </div>
        @endif

        @if ($ready->isNotEmpty() || $accepted->isNotEmpty())
            <ul class="mt-2 divide-y divide-stone-200 dark:divide-stone-800">
                @foreach ($changes->whereIn('status', [\App\Models\TailoredChange::READY, \App\Models\TailoredChange::ACCEPTED]) as $change)
                    <li wire:key="tailored-{{ $change->id }}" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="muted text-xs font-semibold">{{ $label($change) }}</p>
                            <p class="mt-1 text-sm leading-relaxed wrap-break-word">
                                @foreach (\App\Support\TextDiff::words($change->original, (string) $change->suggestion) as $part)
                                    @if ($part['text'] === "\n")
                                        <br>
                                    @elseif ($part['type'] === 'removed')
                                        <del class="rounded bg-red-50 text-red-800 decoration-red-400 dark:bg-red-950 dark:text-red-300"><span class="sr-only">{{ __('removed:') }}</span>{{ $part['text'] }}</del>
                                    @elseif ($part['type'] === 'added')
                                        <ins class="rounded bg-brand-100 text-brand-900 no-underline dark:bg-brand-900 dark:text-brand-100"><span class="sr-only">{{ __('added:') }}</span>{{ $part['text'] }}</ins>
                                    @else
                                        {{ $part['text'] }}
                                    @endif
                                @endforeach
                            </p>
                            @if ($change->status === \App\Models\TailoredChange::READY && ($change->flags !== [] || ! $change->checked))
                                <div id="tailored-facts-{{ $change->id }}" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    @if (! $change->checked)
                                        <p>{{ __('This suggestion could not be checked against your profile.') }}</p>
                                    @else
                                        <p class="font-semibold">{{ __('Not in your profile:') }}</p>
                                        <ul class="mt-1 list-disc pl-5">
                                            @foreach ($change->flags as $flag)
                                                <li>“{{ $flag['quote'] }}”</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($change->confirmed)
                                        <p class="mt-2 font-semibold">{{ __('Confirmed as true.') }}</p>
                                    @else
                                        <button type="button" wire:click="confirm({{ $change->id }})" class="button button-small mt-2">{{ __('This is true, I confirm it') }}</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($change->status === \App\Models\TailoredChange::ACCEPTED)
                                <span class="badge">{{ __('Accepted') }}</span>
                                <button type="button" wire:click="reject({{ $change->id }})" class="button button-small">{{ __('Undo') }}</button>
                            @else
                                <button type="button" wire:click="accept({{ $change->id }})" @disabled($change->blocked()) @if ($change->blocked()) aria-describedby="tailored-facts-{{ $change->id }}" @endif class="button button-small button-primary">{{ __('Accept') }}</button>
                                <button type="button" wire:click="reject({{ $change->id }})" class="button button-small">{{ __('Reject') }}</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
