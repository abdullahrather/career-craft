<div>
    <x-profile.section id="links" :title="__('Links')" :description="__('LinkedIn, GitHub, a portfolio or anything else worth a look.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a link')">
        <ul class="mt-4 flex flex-col gap-2">
            @foreach ($entries as $link)
                <li wire:key="link-{{ $link->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 px-3 py-2 dark:border-stone-800">
                    <div class="min-w-0">
                        <span class="font-semibold">{{ $link->label }}</span>
                        <a href="{{ $link->url }}" class="link ml-2 truncate text-sm" rel="noopener noreferrer" target="_blank">{{ $link->url }}</a>
                    </div>
                    <x-profile.entry-actions :id="$link->id" :name="$link->label" :first="$loop->first" :last="$loop->last" />
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.label" :label="__('Label')" :hint="__('For example LinkedIn or Portfolio.')" required />
                <x-wire.input model="form.url" type="url" :label="__('Address')" placeholder="https://" required />
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
