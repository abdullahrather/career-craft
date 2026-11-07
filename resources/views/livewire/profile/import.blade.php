@php
    use App\Import\ProfileDraft;
    use App\Livewire\Profile\Languages;
    use App\Models\ProfileImport;
    use App\Support\Countries;
    use Illuminate\Support\Carbon;

    $month = fn (mixed $value) => is_string($value) && $value !== '' ? Carbon::createFromFormat('Y-m-d', $value.'-01') : null;
    $sections = [
        'experiences' => __('Work experience'),
        'educations' => __('Education'),
        'skills' => __('Skills'),
        'languages' => __('Languages'),
        'projects' => __('Projects'),
        'certifications' => __('Certificates'),
        'links' => __('Links'),
    ];
    $reasons = [
        ProfileDraft::NO_START => __(':name has no start date, so it was left out. Add it by hand.'),
        ProfileDraft::BAD_DATES => __('The dates of :name do not add up, so it was left out. Add it by hand.'),
        ProfileDraft::NO_LEVEL => __(':name has no level, so it was left out. Add it by hand with its level.'),
        ProfileDraft::INCOMPLETE => __(':name is missing details, so it was left out. Add it by hand.'),
    ];
    $profile = auth()->user()->careerProfile();
    $existing = $draft !== null ? ProfileDraft::existing($profile) : [];
@endphp

<div>
    @if ($import === null)
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card" aria-labelledby="from-cv">
                <h2 id="from-cv" class="card-title">{{ __('From your CV') }}</h2>
                <p class="muted mt-2 text-sm">{{ __('A PDF or Word file, up to 5 MB. The AI reads it and suggests what to fill in. The file itself is not kept.') }}</p>
                <label class="button button-primary mt-5 cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-600">
                    <input type="file" wire:model="cv" accept=".pdf,.docx" class="sr-only">
                    {{ __('Choose a CV file') }}
                </label>
                <p wire:loading wire:target="cv" class="muted mt-3 text-sm" role="status">{{ __('Uploading…') }}</p>
                @error('cv')
                    <p class="field-error mt-3 text-sm" role="alert">{{ $message }}</p>
                @enderror
            </section>

            <section class="card" aria-labelledby="from-linkedin">
                <h2 id="from-linkedin" class="card-title">{{ __('From LinkedIn') }}</h2>
                <ol class="muted mt-2 list-decimal space-y-1 pl-5 text-sm">
                    <li>{{ __('On LinkedIn, open Settings, then Data privacy, then Get a copy of your data.') }}</li>
                    <li>{{ __('Choose your profile data, or everything, and ask for the archive.') }}</li>
                    <li>{{ __('LinkedIn emails you a link to a ZIP file, which can take up to a day. Upload that file here as it is.') }}</li>
                </ol>
                <label class="button mt-5 cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-600">
                    <input type="file" wire:model="linkedin" accept=".zip" class="sr-only">
                    {{ __('Choose the LinkedIn ZIP file') }}
                </label>
                <p wire:loading wire:target="linkedin" class="muted mt-3 text-sm" role="status">{{ __('Uploading…') }}</p>
                @error('linkedin')
                    <p class="field-error mt-3 text-sm" role="alert">{{ $message }}</p>
                @enderror
            </section>
        </div>
    @elseif ($import->status === ProfileImport::READING)
        <section class="card flex items-start gap-4" wire:poll.2s="refresh" role="status">
            <span class="mt-1 size-5 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
            <div>
                <h2 class="card-title">{{ __('Reading :file', ['file' => $import->filename]) }}</h2>
                <p class="muted mt-2 text-sm">{{ __('The AI is going through your CV. This usually takes under a minute, and a few minutes on a slower computer. You can leave this page and come back.') }}</p>
                <button type="button" wire:click="discard" class="button button-small mt-4">{{ __('Cancel') }}</button>
            </div>
        </section>
    @elseif ($import->status === ProfileImport::FAILED)
        <section class="card" role="alert">
            <h2 class="card-title">{{ __('We could not read this CV') }}</h2>
            <p class="muted mt-2 text-sm">{{ __('The AI did not answer in time, or its answer could not be used. Try again, or try the Word file if you uploaded a PDF.') }}</p>
            <button type="button" wire:click="discard" class="button button-primary mt-5">{{ __('Try another file') }}</button>
        </section>
    @elseif ($draft !== null)
        <form wire:submit="apply" class="flex flex-col gap-6">
            <p class="notice">{{ __('Found in :file. Tick what you want in your profile and correct anything that is wrong. Nothing is saved until you add it.', ['file' => $import->filename]) }}</p>

            @if ($personal !== [])
                <section class="card" aria-labelledby="found-personal">
                    <h2 id="found-personal" class="card-title">{{ __('About you') }}</h2>
                    <div class="mt-4 flex flex-col gap-4">
                        @foreach (array_intersect_key($personal, $labels) as $field => $value)
                            <div wire:key="personal-{{ $field }}" class="flex items-start gap-3">
                                <input type="checkbox" id="use-{{ $field }}" wire:model="usePersonal.{{ $field }}" class="mt-9 rounded border-stone-300" aria-label="{{ __('Use :field', ['field' => $labels[$field]]) }}">
                                <div class="min-w-0 flex-1">
                                    @if ($field === 'summary')
                                        <x-wire.textarea :model="'personal.'.$field" :label="$labels[$field]" rows="4" />
                                    @elseif ($field === 'country')
                                        <x-wire.select :model="'personal.'.$field" :label="$labels[$field]" :options="Countries::options()" />
                                    @else
                                        <x-wire.input :model="'personal.'.$field" :label="$labels[$field]" />
                                    @endif
                                    @php($current = (string) $profile->getAttribute($field))
                                    @if ($current !== '')
                                        <p class="muted mt-1 text-xs">{{ __('Now in your profile: :value', ['value' => $field === 'country' ? Countries::name($current) : Str::limit($current, 120)]) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @foreach ($sections as $section => $title)
                @if (($draft['entries'][$section] ?? []) !== [])
                    <section class="card" aria-labelledby="found-{{ $section }}">
                        <h2 id="found-{{ $section }}" class="card-title">{{ $title }}</h2>
                        <ul @class(['mt-4', 'flex flex-wrap gap-2' => $section === 'skills', 'flex flex-col gap-3' => $section !== 'skills'])>
                            @foreach ($draft['entries'][$section] as $index => $entry)
                                @php($known = in_array(ProfileDraft::key($section, $entry), $existing[$section] ?? [], true))
                                <li wire:key="{{ $section }}-{{ $index }}">
                                    <label @class([
                                        'flex gap-3 rounded-xl border border-stone-200 dark:border-stone-800',
                                        'items-center px-3 py-1.5 text-sm' => $section === 'skills',
                                        'items-start p-3' => $section !== 'skills',
                                        'opacity-60' => $known,
                                    ])>
                                        <input type="checkbox" wire:model="use.{{ $section }}.{{ $index }}" @disabled($known) @class(['rounded border-stone-300', 'mt-1' => $section !== 'skills'])>
                                        <span class="min-w-0">
                                            @switch($section)
                                                @case('experiences')
                                                    <span class="block font-semibold">{{ $entry['title'] }}</span>
                                                    <span class="block text-sm">{{ $entry['employer'] }}@if ($entry['city'] !== ''), {{ $entry['city'] }}@endif</span>
                                                    <x-profile.period :from="$month($entry['starts_on'])" :to="$month($entry['ends_on'])" class="block" />
                                                    @if ($entry['achievements'] !== [])
                                                        <span class="muted mt-1 block text-sm">{{ trans_choice('{1} One achievement|[2,*] :count achievements', count($entry['achievements'])) }}: {{ Str::limit(implode(' · ', $entry['achievements']), 160) }}</span>
                                                    @endif
                                                    @break
                                                @case('educations')
                                                    <span class="block font-semibold">{{ $entry['degree'] }}@if ($entry['field'] !== ''), {{ $entry['field'] }}@endif</span>
                                                    <span class="block text-sm">{{ $entry['institution'] }}</span>
                                                    <x-profile.period :from="$month($entry['starts_on'])" :to="$month($entry['ends_on'])" class="block" />
                                                    @break
                                                @case('skills')
                                                    <span class="font-semibold">{{ $entry['name'] }}</span>
                                                    @break
                                                @case('languages')
                                                    <span class="font-semibold">{{ $entry['name'] }}</span>
                                                    <span class="badge badge-brand ml-2">{{ Languages::levels()[$entry['level']] ?? '' }}</span>
                                                    @break
                                                @case('projects')
                                                    <span class="block font-semibold">{{ $entry['name'] }}</span>
                                                    @if ($entry['role'] !== '')
                                                        <span class="block text-sm">{{ $entry['role'] }}</span>
                                                    @endif
                                                    @if ($entry['description'] !== '')
                                                        <span class="muted block text-sm">{{ Str::limit($entry['description'], 160) }}</span>
                                                    @endif
                                                    @break
                                                @case('certifications')
                                                    <span class="block font-semibold">{{ $entry['name'] }}</span>
                                                    <span class="block text-sm">{{ implode(', ', array_filter([$entry['issuer'], $month($entry['issued_on'])?->translatedFormat('M Y')])) }}</span>
                                                    @break
                                                @default
                                                    <span class="block font-semibold">{{ $entry['label'] }}</span>
                                                    <span class="muted block break-all text-sm">{{ $entry['url'] }}</span>
                                            @endswitch
                                            @if ($known)
                                                <span class="badge mt-1">{{ __('Already in your profile') }}</span>
                                            @endif
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @if ($draft['skipped'] !== [])
                <section class="card" aria-labelledby="found-skipped">
                    <h2 id="found-skipped" class="card-title">{{ __('Left out') }}</h2>
                    <ul class="muted mt-3 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($draft['skipped'] as $skipped)
                            <li>{{ strtr($reasons[$skipped['reason']] ?? $reasons[ProfileDraft::INCOMPLETE], [':name' => $skipped['name']]) }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($personal === [] && collect($draft['entries'])->flatten(1)->isEmpty())
                <p class="card muted">{{ __('Nothing new was found: your profile already has everything in this file.') }}</p>
            @endif

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="button button-primary">{{ __('Add to my profile') }}</button>
                <button type="button" wire:click="discard" wire:confirm="{{ __('Discard everything that was found?') }}" class="button">{{ __('Discard') }}</button>
            </div>
        </form>
    @endif
</div>
