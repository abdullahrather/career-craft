@php
    use App\Models\Resume;

    $sectionNames = [
        'summary' => __('Summary'),
        'experiences' => __('Work experience'),
        'educations' => __('Education'),
        'skills' => __('Skills'),
        'languages' => __('Languages'),
        'projects' => __('Projects'),
        'certifications' => __('Certificates'),
        'links' => __('Links'),
    ];
    $fontNames = ['instrument-sans' => 'Instrument Sans', 'ibm-plex-sans' => 'IBM Plex Sans', 'source-serif-4' => 'Source Serif 4', 'lora' => 'Lora'];
    $accentNames = [
        '#1f2937' => __('Ink'),
        '#0f766e' => __('Teal'),
        '#1e3a8a' => __('Navy'),
        '#881337' => __('Burgundy'),
        '#166534' => __('Green'),
        '#6b21a8' => __('Purple'),
        '#9a3412' => __('Rust'),
    ];
    $choice = 'flex cursor-pointer items-center gap-2 rounded-xl border border-stone-300 px-3 py-2 text-sm font-normal has-checked:border-brand-600 has-checked:bg-brand-50 dark:border-stone-700 dark:has-checked:bg-brand-950';
@endphp

<div>
    <h1 class="text-3xl font-semibold sm:text-4xl">{{ $name }}</h1>

    {{-- minmax(0, 1fr): the A4 frame is wider than the column and would stretch it instead of being scaled. --}}
    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <section class="card flex flex-col gap-4" aria-labelledby="cv-basics">
                <h2 id="cv-basics" class="card-title">{{ __('Basics') }}</h2>
                <label class="field" for="cv-name">
                    <span>{{ __('Name') }}</span>
                    {{-- In Livewire 4, .blur alone only syncs in the browser; .live sends it. --}}
                    <input id="cv-name" type="text" wire:model.live.blur="name" maxlength="120" required>
                </label>
                <fieldset class="field">
                    <legend class="mb-1.5">{{ __('Language of the headings and dates') }}</legend>
                    <div class="flex gap-2">
                        @foreach (['en' => 'English', 'de' => 'Deutsch'] as $code => $label)
                            <label class="{{ $choice }}"><input type="radio" wire:model.live="language" value="{{ $code }}"> {{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <section class="card flex flex-col gap-3" aria-labelledby="cv-template">
                <h2 id="cv-template" class="card-title">{{ __('Template') }}</h2>
                @foreach (Resume::templateOptions() as $key => $option)
                    <label class="{{ $choice }} items-start">
                        <input type="radio" wire:model.live="template" value="{{ $key }}" class="mt-1">
                        <span><span class="block font-semibold">{{ $option['name'] }}</span><span class="muted block">{{ $option['description'] }}</span></span>
                    </label>
                @endforeach
            </section>

            <section class="card flex flex-col gap-4" aria-labelledby="cv-look">
                <h2 id="cv-look" class="card-title">{{ __('Look') }}</h2>
                <label class="field" for="cv-font">
                    <span>{{ __('Font') }}</span>
                    <select id="cv-font" wire:model.live="font">
                        @foreach ($fontNames as $alias => $label)
                            <option value="{{ $alias }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="field">
                    <legend class="mb-1.5">{{ __('Colour') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($accentNames as $hex => $label)
                            <label class="cursor-pointer" title="{{ $label }}">
                                <input type="radio" wire:model.live="accent" value="{{ $hex }}" class="peer sr-only" aria-label="{{ $label }}">
                                <span class="block size-8 rounded-full ring-2 ring-transparent ring-offset-2 peer-checked:ring-stone-900 peer-focus-visible:ring-brand-600 dark:ring-offset-stone-900 dark:peer-checked:ring-white" style="background: {{ $hex }}"></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="field">
                    <legend class="mb-1.5">{{ __('Length') }}</legend>
                    <div class="flex gap-2">
                        <label class="{{ $choice }}"><input type="radio" wire:model.live="pages" value="1"> {{ __('One page') }}</label>
                        <label class="{{ $choice }}"><input type="radio" wire:model.live="pages" value="2"> {{ __('Two pages') }}</label>
                    </div>
                    <span class="field-hint">{{ __('One page uses smaller text and tighter spacing.') }}</span>
                </fieldset>
            </section>

            <section class="card" aria-labelledby="cv-sections">
                <h2 id="cv-sections" class="card-title">{{ __('Sections') }}</h2>
                <p class="muted mt-1 text-sm">{{ __('Choose what to show and in which order. Empty sections are left out by themselves.') }}</p>
                <ul class="mt-4 flex flex-col gap-2">
                    @foreach ($sections as $section)
                        <li wire:key="section-{{ $section }}" class="flex items-center justify-between gap-2 rounded-xl border border-stone-200 px-3 py-2 dark:border-stone-800">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:click="toggle('{{ $section }}')" @checked(! in_array($section, $hidden, true)) class="rounded border-stone-300">
                                {{ $sectionNames[$section] }}
                            </label>
                            <span class="flex gap-1">
                                <button type="button" wire:click="move('{{ $section }}', -1)" class="button button-small" @disabled($loop->first) aria-label="{{ __('Move :name up', ['name' => $sectionNames[$section]]) }}">↑</button>
                                <button type="button" wire:click="move('{{ $section }}', 1)" class="button button-small" @disabled($loop->last) aria-label="{{ __('Move :name down', ['name' => $sectionNames[$section]]) }}">↓</button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card flex flex-col gap-3" aria-labelledby="cv-extras">
                <h2 id="cv-extras" class="card-title">{{ __('Extras') }}</h2>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model.live="photo" class="mt-0.5 rounded border-stone-300" @disabled(! $hasPhoto)>
                    <span>
                        {{ __('Show my photo') }}
                        @unless ($hasPhoto)
                            <span class="muted block"><a href="{{ route('profile') }}#photo" class="link">{{ __('Add a photo to your profile first.') }}</a></span>
                        @endunless
                    </span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="signature" class="rounded border-stone-300">
                    {{ __('Add a place, date and signature line at the end') }}
                </label>
            </section>

            @if ($errors->any())
                <p class="alert" role="alert">{{ $errors->first() }}</p>
            @endif
        </div>

        {{-- Livewire leaves the preview alone: Alpine owns the frame and reloads it after each save. --}}
        <section class="lg:sticky lg:top-6" aria-labelledby="cv-preview" wire:ignore x-data="resumePreview(@js(route('resumes.preview', ['id' => $resume->id])))" @resume-saved.window="reload()">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 id="cv-preview" class="card-title">{{ __('Preview') }}</h2>
                <p class="text-sm" role="status" x-show="pages" style="display: none">
                    <span class="badge" :class="pages > target ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' : 'badge-brand'"
                        x-text="pages === 1 ? @js(__('1 page')) : @js(__(':count pages')).replace(':count', pages)"></span>
                    <span x-show="pages > target" class="muted ml-2">{{ __('Longer than you chose. Hide a section or shorten some entries.') }}</span>
                </p>
            </div>
            <div x-ref="box" class="min-w-0 overflow-hidden rounded-2xl border border-stone-200 bg-stone-200 shadow-sm dark:border-stone-800 dark:bg-stone-800" :style="`height: ${height * scale}px`">
                <iframe x-ref="frame" :src="src" title="{{ __('Preview of :name', ['name' => $resume->name]) }}" class="origin-top-left border-0 bg-white" style="width: 794px" :style="`width: 794px; height: ${height}px; transform: scale(${scale})`" @load="loaded()"></iframe>
            </div>
        </section>
    </div>
</div>
