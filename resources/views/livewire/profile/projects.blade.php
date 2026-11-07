<div>
    <x-profile.section id="projects" :title="__('Projects')" :description="__('Work or side projects that show what you can do.')" :empty="$entries->isEmpty()" :open="$open" :add-label="__('Add a project')">
        <ul class="mt-4 divide-y divide-stone-200 dark:divide-stone-800">
            @foreach ($entries as $project)
                <li wire:key="project-{{ $project->id }}" class="py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $project->name }}@if ($project->role) · <span class="font-normal">{{ $project->role }}</span>@endif</p>
                            @if ($project->url)
                                <a href="{{ $project->url }}" class="link text-sm" rel="noopener noreferrer" target="_blank">{{ $project->url }}</a>
                            @endif
                            <div><x-profile.period :from="$project->starts_on" :to="$project->ends_on" /></div>
                        </div>
                        <x-profile.entry-actions :id="$project->id" :name="$project->name" :first="$loop->first" :last="$loop->last" />
                    </div>
                    @if ($project->achievements !== [])
                        <ul class="muted mt-2 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($project->achievements as $achievement)
                                <li>{{ $achievement }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($open)
            <form wire:submit="save" class="mt-4 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-950">
                <x-wire.input model="form.name" :label="__('Project name')" required />
                <x-wire.input model="form.role" :label="__('Your role')" />
                <div class="sm:col-span-2">
                    <x-wire.input model="form.url" type="url" :label="__('Link')" placeholder="https://" />
                </div>
                <x-wire.input model="form.starts_on" type="month" :label="__('Start')" />
                <x-wire.input model="form.ends_on" type="month" :label="__('End')" />
                <div class="sm:col-span-2">
                    <x-wire.textarea model="form.achievements" :label="__('Achievements')" :hint="__('One per line.')" rows="4" />
                </div>
                <div class="sm:col-span-2">
                    <x-wire.textarea model="form.description" :label="__('Description (optional)')" rows="3" />
                </div>
                <x-profile.form-buttons />
            </form>
        @endif
    </x-profile.section>
</div>
