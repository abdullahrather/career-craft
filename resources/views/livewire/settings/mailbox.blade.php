@php
    $names = ['ssl' => __('SSL/TLS (usually port 993)'), 'starttls' => __('STARTTLS (usually port 143)'), 'none' => __('None (only a test server on this machine)')];
@endphp

<section class="card" aria-labelledby="mailbox">
    <h2 id="mailbox" class="card-title">{{ __('Replies from employers') }}</h2>
    <p class="muted mt-2 text-sm">{{ __('Connect your mailbox, and replies to your applications appear on them by themselves. CareerCraft looks at the sender and subject of new emails; only those about one of your applications are read and kept, and they stay unread in your mailbox.') }}</p>
    <p class="muted mt-2 text-sm">{{ __('Your provider\'s help pages list its IMAP server. Gmail, Outlook and most others need an app password for this, such as imap.gmail.com, port 993, SSL/TLS.') }}</p>

    @if ($mailbox !== null)
        <p class="mt-3 text-sm">
            @if ($mailbox->error !== null)
                <span class="badge">{{ __('Not checking') }}</span> {{ $mailbox->error }}
            @else
                <span class="badge badge-brand">{{ __('Connected') }}</span>
                {{ $mailbox->checked_at !== null ? __(':address, last checked :date.', ['address' => $mailbox->username, 'date' => $mailbox->checked_at->translatedFormat('j M Y, H:i')]) : __(':address, not checked yet.', ['address' => $mailbox->username]) }}
            @endif
        </p>
    @endif

    <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
        <x-wire.input model="boxHost" :label="__('Mail server')" :hint="__('Such as imap.gmail.com or imap.gmx.net')" />
        <x-wire.input model="boxPort" type="number" :label="__('Port')" />
        <label class="field sm:col-span-2" for="boxSecurity">
            <span>{{ __('Encryption') }}</span>
            <select id="boxSecurity" wire:model="boxSecurity">
                @foreach ($securities as $value)
                    <option value="{{ $value }}">{{ $names[$value] }}</option>
                @endforeach
            </select>
        </label>
        <x-wire.input model="boxUsername" :label="__('User name')" :hint="__('Usually your email address')" />
        <x-wire.input model="boxPassword" type="password" :label="__('Password')" :hint="$mailbox !== null ? __('Leave empty to keep the saved one.') : __('Stored encrypted.')" autocomplete="new-password" />
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Connect') }}</span>
                <span wire:loading wire:target="save">{{ __('Logging in…') }}</span>
            </button>
            @if ($mailbox !== null)
                <button type="button" wire:click="check" class="button" wire:loading.attr="disabled" wire:target="check">
                    <span wire:loading.remove wire:target="check">{{ __('Check now') }}</span>
                    <span wire:loading wire:target="check">{{ __('Checking…') }}</span>
                </button>
                <button type="button" wire:click="remove" wire:confirm="{{ __('Disconnect this mailbox? Replies already found stay on their applications.') }}" class="button button-danger">{{ __('Disconnect') }}</button>
            @endif
        </div>
        <div class="sm:col-span-2" aria-live="polite">
            @error('boxTest')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror
            @if (session('mailbox-saved'))
                <p class="notice" role="status">{{ session('mailbox-saved') }}</p>
            @endif
        </div>
    </form>
</section>
