@php
    $names = ['ssl' => __('SSL/TLS (usually port 465)'), 'starttls' => __('STARTTLS (usually port 587)'), 'none' => __('None (only a test server on this machine)')];
@endphp

<section class="card" aria-labelledby="mail-account">
    <h2 id="mail-account" class="card-title">{{ __('Sending email') }}</h2>
    <p class="muted mt-2 text-sm">{{ __('Send applications from your own address, through your mail provider\'s server. Your provider\'s help pages list the server, port and encryption; some, such as Gmail, need an app password.') }}</p>
    @if ($account?->verified_at !== null)
        <p class="mt-3 text-sm"><span class="badge badge-brand">{{ __('Ready') }}</span> {{ __('Sending from :address, tested :date.', ['address' => $account->from_address, 'date' => $account->verified_at->translatedFormat('j M Y, H:i')]) }}</p>
    @elseif ($account !== null)
        <p class="mt-3 text-sm"><span class="badge">{{ __('Not tested') }}</span> {{ __('The last test email didn\'t go through. Check the settings and save again.') }}</p>
    @endif

    <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
        <x-wire.input model="mailHost" :label="__('Mail server')" :hint="__('Such as smtp.gmail.com or mail.gmx.net')" />
        <x-wire.input model="mailPort" type="number" :label="__('Port')" />
        <label class="field sm:col-span-2" for="mailSecurity">
            <span>{{ __('Encryption') }}</span>
            <select id="mailSecurity" wire:model="mailSecurity">
                @foreach ($securities as $value)
                    <option value="{{ $value }}">{{ $names[$value] }}</option>
                @endforeach
            </select>
        </label>
        <x-wire.input model="mailUsername" :label="__('User name')" :hint="__('Usually your email address')" />
        <x-wire.input model="mailPassword" type="password" :label="__('Password')" :hint="$account !== null ? __('Leave empty to keep the saved one.') : __('Stored encrypted.')" autocomplete="new-password" />
        <x-wire.input model="mailFrom" type="email" :label="__('Send from')" />
        <x-wire.input model="mailName" :label="__('Your name')" />
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Save and send a test email') }}</span>
                <span wire:loading wire:target="save">{{ __('Sending a test email…') }}</span>
            </button>
            @if ($account !== null)
                <button type="button" wire:click="remove" wire:confirm="{{ __('Remove this mail account?') }}" class="button button-danger">{{ __('Remove') }}</button>
            @endif
        </div>
        <div class="sm:col-span-2" aria-live="polite">
            @error('test')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror
            @if (session('mail-saved'))
                <p class="notice" role="status">{{ session('mail-saved') }}</p>
            @endif
        </div>
    </form>
</section>
