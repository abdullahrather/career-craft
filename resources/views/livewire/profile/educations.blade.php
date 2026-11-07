<div>
    <x-profile.section id="education" :title="__('Education')" :description="__('Degrees, apprenticeships and school.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add education')">
        <ul class="mt-4 divide-y divide-stone-200 dark:divide-stone-800">
            @foreach ($entries as $education)
                <li wire:key="education-{{ $education->id }}" class="flex flex-wrap items-start justify-between gap-3 py-4">
                    <div>
                        <p class="font-semibold">{{ $education->degree }}@if ($education->field), {{ $education->field }}@endif</p>
                        <p class="text-sm">{{ $education->institution }}@if ($education->grade) · {{ __('Grade :grade', ['grade' => $education->grade]) }}@endif</p>
                        <x-profile.period :from="$education->starts_on" :to="$education->ends_on" />
                    </div>
                    <x-profile.entry-actions :id="$education->id" :name="$education->degree" :first="$loop->first" :last="$loop->last" />
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.degree" :label="__('Degree or qualification')" required />
                <x-wire.input model="form.field" :label="__('Field of study')" />
                <x-wire.input model="form.institution" :label="__('School or university')" required />
                <x-wire.input model="form.grade" :label="__('Grade')" :hint="__('For example 1,7 or First-class honours.')" />
                <x-wire.input model="form.city" :label="__('City')" />
                <x-wire.select model="form.country" :label="__('Country')" :options="\App\Support\Countries::options()" :placeholder="__('Choose a country')" />
                <x-wire.input model="form.starts_on" type="month" :label="__('Start')" required />
                <x-wire.input model="form.ends_on" type="month" :label="__('End')" :hint="__('Leave empty while you are still studying.')" />
                <div class="sm:col-span-2">
                    <x-wire.textarea model="form.description" :label="__('Description (optional)')" rows="3" />
                </div>
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
