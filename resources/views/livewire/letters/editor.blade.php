@php
    $open = $letter->openFlags();
    $failures = [
        'limit' => __('You have used today\'s AI allowance. It starts again at midnight.'),
        'ai' => __('The AI could not write the letter. Try again in a moment.'),
    ];
@endphp

<div>
    @if ($letter->status === \App\Models\CoverLetter::WRITING)
        <section class="card flex items-start gap-4" wire:poll.3s role="status">
            <span class="mt-1 size-5 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
            <div>
                <h1 class="card-title">{{ __('Writing your cover letter…') }}</h1>
                <p class="muted mt-1 text-sm">{{ __('The AI writes it, then every paragraph is checked against your profile. This takes up to a few minutes with the local AI. You can leave this page.') }}</p>
            </div>
        </section>
    @elseif ($letter->status === \App\Models\CoverLetter::FAILED)
        <section class="card">
            <h1 class="card-title">{{ __('The cover letter could not be written') }}</h1>
            <p class="alert mt-3">{{ $failures[$letter->error] ?? $failures['ai'] }}</p>
            @error('letter')
                <p class="field-error mt-2 text-sm" role="alert">{{ $message }}</p>
            @enderror
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="retry" class="button button-primary">{{ __('Try again') }}</button>
                <button type="button" wire:click="delete" class="button button-danger">{{ __('Delete') }}</button>
            </div>
        </section>
    @else
        <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[24rem_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <section class="card flex flex-col gap-4" aria-labelledby="letter-title">
                    <div>
                        <h1 id="letter-title" class="card-title">{{ $letter->language === 'de' ? __('Cover letter (German)') : __('Cover letter (English)') }}</h1>
                        @if ($letter->jobPost !== null)
                            <p class="muted mt-1 text-sm wrap-break-word">{{ implode(' · ', array_filter([$letter->jobPost->title, $letter->jobPost->company])) }}</p>
                        @endif
                    </div>

                    @if (! $letter->checked && ! in_array(\App\Models\CoverLetter::UNCHECKED, $letter->confirmed, true))
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                            <p>{{ __('Some of this letter could not be checked against your profile. Read it carefully.') }}</p>
                            <button type="button" wire:click="confirmUnchecked" class="button button-small mt-2">{{ __('I have read it, it is true') }}</button>
                        </div>
                    @endif

                    @if ($open !== [])
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200" role="status">
                            <p class="font-semibold">{{ __('Not in your profile:') }}</p>
                            <ul class="mt-2 flex flex-col gap-2">
                                @foreach ($open as $flag)
                                    <li wire:key="flag-{{ md5($flag['quote']) }}" class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="min-w-0 wrap-break-word">“{{ $flag['quote'] }}”</span>
                                        <button type="button" wire:click="confirm(@js($flag['quote']))" class="button button-small">{{ __('This is true') }}</button>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2">{{ __('Confirm each one, or take it out of the letter and save.') }}</p>
                        </div>
                    @endif

                    <form wire:submit="save" class="flex flex-col gap-4">
                        <x-wire.textarea model="recipient" :label="__('Recipient')" rows="3" :hint="__('The address block, one line each: company, street, postcode and city.')" />
                        <x-wire.input model="contact" :label="__('Contact person')" :hint="__('Greeted by name when given, such as Anna Schulz.')" />
                        <x-wire.input model="subject" :label="__('Subject')" />
                        <x-wire.textarea model="body" :label="__('Letter')" rows="14" :hint="__('An empty line between paragraphs. The greeting and closing are added for you.')" />
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="button button-primary">{{ __('Save') }}</button>
                            <span wire:loading wire:target="save" class="muted text-sm">{{ __('Saving…') }}</span>
                        </div>
                    </form>
                </section>

                <section class="card flex flex-col gap-3" aria-labelledby="letter-download">
                    <h2 id="letter-download" class="card-title">{{ __('Download') }}</h2>
                    @if ($letter->sendable())
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('letters.download', ['id' => $letter->id, 'with' => 'letter']) }}" class="button">{{ __('Letter (PDF)') }}</a>
                            @if ($letter->resume !== null)
                                <a href="{{ route('letters.download', ['id' => $letter->id, 'with' => 'cv']) }}" class="button button-primary">{{ __('Letter and CV (PDF)') }}</a>
                            @endif
                        </div>
                        @if ($letter->resume !== null)
                            <p class="muted text-sm">{{ __('With the CV :name, as it is now.', ['name' => $letter->resume->name]) }}</p>
                        @endif
                    @else
                        <p class="muted text-sm">{{ __('Confirm or remove what is marked above first.') }}</p>
                    @endif
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this cover letter?') }}" class="button button-danger button-small self-start">{{ __('Delete') }}</button>
                </section>
            </div>

            {{-- Livewire leaves the preview alone: Alpine owns the frame and reloads it after each save. --}}
            <section class="lg:sticky lg:top-6" aria-labelledby="letter-preview" wire:ignore x-data="resumePreview(@js(route('letters.preview', ['id' => $letter->id])))" @letter-saved.window="reload()">
                <h2 id="letter-preview" class="card-title mb-3">{{ __('Preview') }}</h2>
                <div x-ref="box" class="min-w-0 overflow-hidden rounded-2xl border border-stone-200 bg-stone-200 shadow-sm dark:border-stone-800 dark:bg-stone-800" :style="`height: ${height * scale}px`">
                    <iframe x-ref="frame" :src="src" title="{{ __('Preview of the cover letter') }}" class="origin-top-left border-0 bg-white" style="width: 794px" :style="`width: 794px; height: ${height}px; transform: scale(${scale})`" @load="loaded()"></iframe>
                </div>
            </section>
        </div>
    @endif
</div>
