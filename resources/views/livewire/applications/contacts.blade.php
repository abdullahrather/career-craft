<section class="card flex flex-col gap-4" aria-labelledby="contacts">
    <h2 id="contacts" class="card-title">{{ __('Contacts') }}</h2>

    @if ($contacts->isNotEmpty())
        <ul class="flex flex-col gap-3 text-sm">
            @foreach ($contacts as $contact)
                <li wire:key="contact-{{ $contact->id }}" class="flex items-start justify-between gap-3">
                    <div class="min-w-0 wrap-break-word">
                        <p class="font-semibold">{{ $contact->name }}@if ($contact->role !== '')<span class="muted font-normal"> · {{ $contact->role }}</span>@endif</p>
                        <p class="flex flex-wrap gap-x-3">
                            @if ($contact->email !== null)
                                <a href="mailto:{{ $contact->email }}" class="link">{{ $contact->email }}</a>
                            @endif
                            @if ($contact->phone !== null)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="link">{{ $contact->phone }}</a>
                            @endif
                            @if ($contact->linkedin !== null)
                                <a href="{{ $contact->linkedin }}" class="link" target="_blank" rel="noopener noreferrer nofollow">LinkedIn ↗</a>
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="remove({{ $contact->id }})" wire:confirm="{{ __('Remove this contact?') }}" class="button button-small shrink-0" aria-label="{{ __('Remove :name', ['name' => $contact->name]) }}">{{ __('Remove') }}</button>
                </li>
            @endforeach
        </ul>
    @endif

    <details @if ($contacts->isEmpty()) open @endif wire:ignore.self>
        <summary class="cursor-pointer text-sm font-semibold">{{ __('Add a contact') }}</summary>
        <form wire:submit="add" class="mt-3 grid gap-4 sm:grid-cols-2">
            <x-wire.input model="name" :label="__('Name')" />
            <x-wire.input model="role" :label="__('Role')" :hint="__('Such as recruiter or hiring manager')" />
            <x-wire.input model="email" type="email" :label="__('Email')" />
            <x-wire.input model="phone" type="tel" :label="__('Phone')" />
            <div class="sm:col-span-2">
                <x-wire.input model="linkedin" type="url" :label="__('LinkedIn profile')" />
            </div>
            <button type="submit" class="button self-start" wire:loading.attr="disabled" wire:target="add">{{ __('Add contact') }}</button>
        </form>
    </details>
</section>
