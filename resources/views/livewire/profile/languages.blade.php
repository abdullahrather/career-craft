<div>
    <x-profile.section id="languages" :title="__('Languages')" :description="__('Levels follow the Common European Framework, which German employers expect.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a language')">
        <ul class="mt-4 flex flex-col gap-2">
            @foreach ($entries as $language)
                <li wire:key="language-{{ $language->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 px-3 py-2 dark:border-stone-800">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold">{{ $language->name }}</span>
                        <span class="badge badge-brand">{{ \App\Livewire\Profile\Languages::levels()[$language->level] ?? $language->level }}</span>
                    </div>
                    <x-profile.entry-actions :id="$language->id" :name="$language->name" :first="$loop->first" :last="$loop->last" />
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.name" :label="__('Language')" required />
                <x-wire.select model="form.level" :label="__('Level')" :options="\App\Livewire\Profile\Languages::levels()" />
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
