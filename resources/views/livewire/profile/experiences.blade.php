<div>
    <x-profile.section id="experience" :title="__('Work experience')" :description="__('Most recent first. Achievements become the bullet points on your CV.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a job')">
        <ul class="mt-4 divide-y divide-stone-200 dark:divide-stone-800">
            @foreach ($entries as $job)
                <li wire:key="experience-{{ $job->id }}" class="py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $job->title }}</p>
                            <p class="text-sm">{{ $job->employer }}@if ($job->city), {{ $job->city }}@endif</p>
                            <x-profile.period :from="$job->starts_on" :to="$job->ends_on" />
                        </div>
                        <x-profile.entry-actions :id="$job->id" :name="$job->title" :first="$loop->first" :last="$loop->last" />
                    </div>
                    @if ($job->achievements !== [])
                        <ul class="muted mt-2 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($job->achievements as $achievement)
                                <li>{{ $achievement }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.title" :label="__('Job title')" required />
                <x-wire.input model="form.employer" :label="__('Employer')" required />
                <x-wire.input model="form.city" :label="__('City')" />
                <x-wire.select model="form.country" :label="__('Country')" :options="\App\Support\Countries::options()" :placeholder="__('Choose a country')" />
                <x-wire.input model="form.starts_on" type="month" :label="__('Start')" required />
                <div class="flex flex-col gap-2">
                    <x-wire.input model="form.ends_on" type="month" :label="__('End')" :disabled="(bool) ($form['current'] ?? false)" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="form.current" class="rounded border-stone-300">
                        {{ __('I work here now') }}
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <x-wire.textarea model="form.achievements" :label="__('Achievements')" :hint="__('One per line. Start with a verb and add a number where you can, such as “Cut load times by 40%”.')" rows="5" />
                </div>
                <div class="sm:col-span-2">
                    <x-wire.textarea model="form.description" :label="__('Description (optional)')" rows="3" />
                </div>
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
