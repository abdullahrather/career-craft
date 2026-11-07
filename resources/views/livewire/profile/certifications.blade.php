<div>
    <x-profile.section id="certifications" :title="__('Certificates')" :description="__('Courses, certificates and licences.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a certificate')">
        <ul class="mt-4 divide-y divide-stone-200 dark:divide-stone-800">
            @foreach ($entries as $certificate)
                <li wire:key="certificate-{{ $certificate->id }}" class="flex flex-wrap items-start justify-between gap-3 py-4">
                    <div>
                        <p class="font-semibold">{{ $certificate->name }}</p>
                        <p class="text-sm">
                            {{ $certificate->issuer }}
                            @if ($certificate->issued_on) · {{ $certificate->issued_on->translatedFormat('M Y') }}@endif
                        </p>
                        @if ($certificate->url)
                            <a href="{{ $certificate->url }}" class="link text-sm" rel="noopener noreferrer" target="_blank">{{ __('View credential') }}</a>
                        @endif
                    </div>
                    <x-profile.entry-actions :id="$certificate->id" :name="$certificate->name" :first="$loop->first" :last="$loop->last" />
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.name" :label="__('Certificate')" required />
                <x-wire.input model="form.issuer" :label="__('Issued by')" />
                <x-wire.input model="form.issued_on" type="month" :label="__('Issued')" />
                <x-wire.input model="form.expires_on" type="month" :label="__('Expires')" :hint="__('Leave empty if it does not expire.')" />
                <div class="sm:col-span-2">
                    <x-wire.input model="form.url" type="url" :label="__('Link to the credential')" placeholder="https://" />
                </div>
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
