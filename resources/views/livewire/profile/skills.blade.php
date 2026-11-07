<div>
    <x-profile.section id="skills" :title="__('Skills')" :description="__('Tools, technologies and abilities. Employers and job portals search for these words.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a skill')">
        <ul class="mt-4 flex flex-col gap-2">
            @foreach ($entries as $skill)
                <li wire:key="skill-{{ $skill->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 px-3 py-2 dark:border-stone-800">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold">{{ $skill->name }}</span>
                        <span class="badge badge-brand">{{ \App\Livewire\Profile\Skills::levels()[$skill->level] ?? '' }}</span>
                    </div>
                    <x-profile.entry-actions :id="$skill->id" :name="$skill->name" :first="$loop->first" :last="$loop->last" />
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.name" :label="__('Skill')" required />
                <x-wire.select model="form.level" :label="__('Level')" :options="\App\Livewire\Profile\Skills::levels()" />
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
