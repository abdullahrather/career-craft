<section class="card flex flex-col gap-4" aria-labelledby="send-email">
    <div>
        <h2 id="send-email" class="card-title">{{ __('Send by email') }}</h2>
        <p class="muted mt-1 text-sm">{{ __('From your own address, with the CV and letter recorded above attached. Nothing is sent until you press Send.') }}</p>
    </div>

    @if ($account?->verified_at === null)
        <p class="text-sm">{{ __('Set up your mail account first, and CareerCraft can send applications from your address.') }} <a href="{{ route('settings') }}" class="link">{{ __('Settings') }}</a></p>
    @elseif ($application->cv_path === null && $application->letter_path === null)
        <p class="text-sm">{{ __('Record the CV and letter you send first, under What you sent.') }}</p>
    @else
        <form wire:submit="send" class="flex flex-col gap-4">
            <x-wire.input model="emailTo" type="email" :label="__('To')" :hint="$emailTo === '' ? __('No address was found in the job post. Copy it from the job\'s page.') : __('Found in the job post. Check it.')" />
            <x-wire.input model="emailSubject" :label="__('Subject')" />
            <x-wire.textarea model="emailBody" :label="__('Text')" rows="8" />
            <p class="muted text-sm">
                {{ __('From') }}: {{ $account->from_name }} &lt;{{ $account->from_address }}&gt; ·
                {{ __('Attached') }}: {{ implode(', ', array_filter([$application->cv_path !== null ? __('CV') : null, $application->letter_path !== null ? __('Letter') : null])) }}
            </p>
            @error('send')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror
            @if (session('emailed'))
                <p class="notice" role="status">{{ session('emailed') }}</p>
            @endif
            <button type="submit" wire:confirm="{{ __('Send this application now?') }}" wire:loading.attr="disabled" wire:target="send" class="button button-primary self-start">
                <span wire:loading.remove wire:target="send">{{ __('Send') }}</span>
                <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
            </button>
        </form>
    @endif

    @if ($emails->isNotEmpty())
        <div class="border-t border-stone-200 pt-4 dark:border-stone-800">
            <h3 class="font-semibold">{{ __('Sent') }}</h3>
            <ul class="mt-2 flex flex-col gap-2 text-sm">
                @foreach ($emails as $email)
                    <li wire:key="email-{{ $email->id }}">
                        <details>
                            <summary class="cursor-pointer wrap-break-word">{{ $email->created_at->translatedFormat('j M Y, H:i') }} · {{ $email->to }} · {{ $email->subject }}</summary>
                            <p class="muted mt-2 text-xs">{{ __('Attached') }}: {{ implode(', ', $email->attachments) }}</p>
                            <p class="mt-2 whitespace-pre-line wrap-break-word">{{ $email->body }}</p>
                        </details>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
