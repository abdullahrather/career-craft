@php
    $labels = [
        'improve' => __('Improve bullets'),
        'rewrite' => __('Rewrite'),
        'tone' => __('Fix tone'),
    ];
@endphp

<div class="mt-2">
    @if ($available)
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Writing assistant') }}">
            <span class="muted text-xs font-semibold">{{ __('Writing assistant') }}</span>
            @foreach ($tools as $tool)
                @if ($tool === 'translate')
                    <button type="button" wire:click="suggest('translate', 'en')" wire:loading.attr="disabled" wire:target="suggest" class="button button-small">{{ __('To English') }}</button>
                    <button type="button" wire:click="suggest('translate', 'de')" wire:loading.attr="disabled" wire:target="suggest" class="button button-small">{{ __('To German') }}</button>
                @else
                    <button type="button" wire:click="suggest('{{ $tool }}')" wire:loading.attr="disabled" wire:target="suggest" class="button button-small">{{ $labels[$tool] }}</button>
                @endif
            @endforeach
            <span wire:loading wire:target="suggest" class="muted text-xs" role="status">{{ __('Writing and checking a suggestion…') }}</span>
        </div>

        @error('writing')
            <p class="field-error mt-2 text-sm" role="alert">{{ $message }}</p>
        @enderror

        <div aria-live="polite">
            @if ($notice !== '')
                <p class="notice mt-3">{{ $notice }}</p>
            @endif

            @if ($changes !== [])
                <div class="mt-3 rounded-xl border border-stone-200 bg-white p-3 dark:border-stone-800 dark:bg-stone-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold">{{ trans_choice('{1} One suggestion|[2,*] :count suggestions', count($changes)) }}</p>
                        @if (count($changes) > 1)
                            <div class="flex gap-2">
                                <button type="button" wire:click="acceptAll" class="button button-small button-primary">{{ __('Accept all') }}</button>
                                <button type="button" wire:click="dismiss" class="button button-small">{{ __('Reject all') }}</button>
                            </div>
                        @endif
                    </div>
                    <ul class="mt-2 divide-y divide-stone-200 dark:divide-stone-800">
                        @foreach ($changes as $index => $change)
                            @php
                                $waiting = ($change['flags'] !== [] || ! $change['checked']) && ! $change['confirmed'];
                            @endphp
                            <li wire:key="change-{{ md5($change['from']) }}" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                <p class="text-sm font-normal leading-relaxed">
                                    @foreach (\App\Support\TextDiff::words($change['from'], $change['to']) as $part)
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
                                @if ($change['flags'] !== [] || ! $change['checked'])
                                    <div id="facts-{{ $index }}" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                        @if (! $change['checked'])
                                            <p>{{ __('This suggestion could not be checked against your profile.') }}</p>
                                        @else
                                            <p class="font-semibold">{{ __('Not in your profile:') }}</p>
                                            <ul class="mt-1 list-disc pl-5">
                                                @foreach ($change['flags'] as $flag)
                                                    <li>“{{ $flag['quote'] }}”</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if ($change['confirmed'])
                                            <p class="mt-2 font-semibold">{{ __('Confirmed as true.') }}</p>
                                        @else
                                            <button type="button" wire:click="confirm({{ $index }})" class="button button-small mt-2">{{ __('This is true, I confirm it') }}</button>
                                        @endif
                                    </div>
                                @endif
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button type="button" wire:click="accept({{ $index }})" @disabled($waiting) @if ($waiting) aria-describedby="facts-{{ $index }}" @endif class="button button-small button-primary">{{ __('Accept') }}</button>
                                    <button type="button" wire:click="reject({{ $index }})" class="button button-small">{{ __('Reject') }}</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
