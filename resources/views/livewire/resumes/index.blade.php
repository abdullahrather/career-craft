@php($templates = \App\Models\Resume::templateOptions())

<div class="grid items-start gap-6 xl:grid-cols-[1fr_24rem]">
    <section aria-labelledby="your-cvs">
        <h2 id="your-cvs" class="sr-only">{{ __('Your CVs') }}</h2>
        @if ($resumes->isEmpty())
            <p class="card muted">{{ __('No CVs yet. Make your first one with the form.') }}</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($resumes as $resume)
                    <li wire:key="resume-{{ $resume->id }}" class="card flex flex-col gap-3">
                        <div>
                            <a href="{{ route('resumes.edit', ['id' => $resume->id]) }}" class="card-title hover:underline">{{ $resume->name }}</a>
                            <p class="mt-2 flex flex-wrap gap-2">
                                <span class="badge badge-brand">{{ $templates[$resume->template]['name'] ?? $resume->template }}</span>
                                <span class="badge">{{ $resume->language === 'de' ? 'Deutsch' : 'English' }}</span>
                            </p>
                            <p class="muted mt-2 text-xs">{{ __('Changed :time', ['time' => $resume->updated_at->diffForHumans()]) }}</p>
                        </div>
                        <div class="mt-auto flex gap-2">
                            <a href="{{ route('resumes.edit', ['id' => $resume->id]) }}" class="button button-small">{{ __('Edit') }}</a>
                            <button type="button" wire:click="delete({{ $resume->id }})" wire:confirm="{{ __('Delete :name? Your profile stays as it is.', ['name' => $resume->name]) }}" class="button button-danger button-small">{{ __('Delete') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <form wire:submit="create" class="card flex flex-col gap-4" aria-labelledby="new-cv">
        <h2 id="new-cv" class="card-title">{{ __('New CV') }}</h2>
        <x-wire.input model="name" :label="__('Name')" :hint="__('Only you see it, such as “Backend roles, German”.')" required />

        <fieldset class="field">
            <legend class="mb-1.5">{{ __('Language of the headings and dates') }}</legend>
            <div class="flex gap-2">
                @foreach (['en' => 'English', 'de' => 'Deutsch'] as $code => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-stone-300 px-3 py-2 font-normal has-checked:border-brand-600 has-checked:bg-brand-50 dark:border-stone-700 dark:has-checked:bg-brand-950">
                        <input type="radio" wire:model="language" value="{{ $code }}"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="field">
            <legend class="mb-1.5">{{ __('Template') }}</legend>
            <div class="flex flex-col gap-2">
                @foreach ($templates as $key => $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-300 p-3 font-normal has-checked:border-brand-600 has-checked:bg-brand-50 dark:border-stone-700 dark:has-checked:bg-brand-950">
                        <input type="radio" wire:model="template" value="{{ $key }}" class="mt-1">
                        <span><span class="block font-semibold">{{ $option['name'] }}</span><span class="muted block text-sm">{{ $option['description'] }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <button type="submit" class="button button-primary self-start">{{ __('Make this CV') }}</button>
    </form>
</div>
