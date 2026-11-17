<section class="card flex flex-col gap-4" aria-labelledby="interviews">
    <h2 id="interviews" class="card-title">{{ __('Interviews') }}</h2>

    @if ($interviews->isNotEmpty())
        <ul class="flex flex-col gap-3 text-sm">
            @foreach ($interviews as $interview)
                <li wire:key="interview-{{ $interview->id }}" class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 wrap-break-word">
                        <p class="font-semibold">{{ $interview->starts_at->translatedFormat('l, j M Y, H:i') }} <span class="muted font-normal">· {{ __(':minutes minutes', ['minutes' => $interview->minutes]) }}</span></p>
                        @if ($interview->location !== '')
                            <p class="muted">{{ $interview->location }}</p>
                        @endif
                    </div>
                    <span class="flex shrink-0 gap-2">
                        <a href="{{ route('applications.interview', ['id' => $applicationId, 'interview' => $interview->id]) }}" class="button button-small">{{ __('Add to calendar') }}</a>
                        <button type="button" wire:click="remove({{ $interview->id }})" wire:confirm="{{ __('Delete this interview?') }}" class="button button-small" aria-label="{{ __('Delete the interview on :date', ['date' => $interview->starts_at->translatedFormat('j M Y, H:i')]) }}">{{ __('Delete') }}</button>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="add" class="grid gap-3 sm:grid-cols-2">
        <x-wire.input model="startsAt" type="datetime-local" :label="__('Date and time')" />
        <x-wire.input model="minutes" type="number" :label="__('Length in minutes')" />
        <div class="sm:col-span-2">
            <x-wire.input model="venue" :label="__('Where')" :hint="__('An address, a phone number or a video link')" />
        </div>
        <button type="submit" class="button self-start justify-self-start" wire:loading.attr="disabled" wire:target="add">{{ __('Add interview') }}</button>
    </form>
</section>
