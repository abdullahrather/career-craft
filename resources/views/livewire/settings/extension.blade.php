<section class="card" aria-labelledby="extension">
    <h2 id="extension" class="card-title">{{ __('Browser extension') }}</h2>
    <p class="muted mt-2 text-sm">{{ __('The CareerCraft extension for Chrome and Edge fills application forms on Greenhouse and Lever with your details, CV and letter. You check the form and submit it yourself. Make a token here and paste it into the extension.') }}</p>

    @if ($token !== null)
        <div class="notice mt-4" role="status">
            <p class="font-semibold">{{ __('Your new token. Copy it now: it isn\'t shown again.') }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="min-w-0 break-all rounded bg-white px-2 py-1 text-xs dark:bg-stone-950">{{ $token }}</code>
                <x-copy-button :text="$token" :label="__('Copy the token')" />
            </div>
        </div>
    @endif

    @if ($tokens->isNotEmpty())
        <ul class="mt-4 flex flex-col gap-2 text-sm">
            @foreach ($tokens as $item)
                <li wire:key="token-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        {{ __('Made :date', ['date' => $item->created_at->translatedFormat('j M Y, H:i')]) }}
                        <span class="muted">· {{ $item->last_used_at !== null ? __('last used :date', ['date' => $item->last_used_at->translatedFormat('j M Y, H:i')]) : __('not used yet') }}</span>
                    </span>
                    <button type="button" wire:click="revoke({{ $item->id }})" wire:confirm="{{ __('Revoke this token? The extension using it stops working.') }}" class="button button-small">{{ __('Revoke') }}</button>
                </li>
            @endforeach
        </ul>
    @endif

    <button type="button" wire:click="create" class="button mt-4">{{ __('Make a token') }}</button>
</section>
