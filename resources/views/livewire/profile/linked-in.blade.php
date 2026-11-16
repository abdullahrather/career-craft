@php
    $labels = ['headline' => __('Headline'), 'about' => __('About')];
@endphp

<section id="linkedin" class="card scroll-mt-6" aria-labelledby="linkedin-title">
    <h2 id="linkedin-title" class="card-title">{{ __('LinkedIn') }}</h2>
    <p class="muted mt-1 text-sm">{{ __('A headline and About section for LinkedIn, written from your profile and checked against it. Copy them into LinkedIn yourself.') }}</p>

    @if (! $available)
        <p class="notice mt-4">{{ __('The AI is not set up yet. An admin can choose a provider under AI settings.') }}</p>
    @else
        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="field">
                <span>{{ __('Language') }}</span>
                <select wire:model="language">
                    <option value="en">{{ __('English') }}</option>
                    <option value="de">{{ __('German') }}</option>
                </select>
            </label>
            <button type="button" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest" class="button button-primary">{{ __('Suggest a headline and About') }}</button>
            <span wire:loading wire:target="suggest" class="muted text-sm" role="status">{{ __('Writing and checking the suggestions…') }}</span>
        </div>

        @error('linkedin')
            <p class="field-error mt-3 text-sm" role="alert">{{ $message }}</p>
        @enderror

        <div aria-live="polite">
            @foreach ($suggestions as $field => $suggestion)
                @php($waiting = ($suggestion['flags'] !== [] || ! $suggestion['checked']) && ! $suggestion['confirmed'])
                <div wire:key="linkedin-{{ $field }}" class="mt-4 rounded-xl border border-stone-200 p-4 dark:border-stone-800" x-data="{ copied: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ $labels[$field] }}</h3>
                        <button type="button" class="button button-small" @disabled($waiting) @if ($waiting) aria-describedby="linkedin-facts-{{ $field }}" @endif
                            x-on:click="navigator.clipboard.writeText($refs.text.innerText).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                            <span x-show="! copied">{{ __('Copy') }}</span>
                            <span x-show="copied" style="display: none">{{ __('Copied') }}</span>
                        </button>
                    </div>
                    <p x-ref="text" class="mt-2 text-sm leading-relaxed whitespace-pre-line wrap-break-word">{{ $suggestion['text'] }}</p>
                    @if ($suggestion['flags'] !== [] || ! $suggestion['checked'])
                        <div id="linkedin-facts-{{ $field }}" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                            @if (! $suggestion['checked'])
                                <p>{{ __('This suggestion could not be checked against your profile.') }}</p>
                            @else
                                <p class="font-semibold">{{ __('Not in your profile:') }}</p>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($suggestion['flags'] as $flag)
                                        <li>“{{ $flag['quote'] }}”</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($suggestion['confirmed'])
                                <p class="mt-2 font-semibold">{{ __('Confirmed as true.') }}</p>
                            @else
                                <button type="button" wire:click="confirm('{{ $field }}')" class="button button-small mt-2">{{ __('This is true, I confirm it') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
