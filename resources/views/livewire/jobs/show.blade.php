@php
    $reasons = [
        'bad-url' => __('That is not a web address we can read. It must start with http:// or https://.'),
        'blocked-address' => __('That address is not a public web page, so it was not opened.'),
        'unreachable' => __('The page could not be opened. Check the address, or paste the post instead.'),
        'not-a-page' => __('The address is not a web page. Paste the post instead.'),
        'too-large' => __('The page is too large to read. Paste the post instead.'),
        'no-text' => __('The page has no text we can read; some job boards build it in the browser. Paste the post instead.'),
        'limit' => __('You have used today\'s AI allowance. It starts again at midnight.'),
        'ai' => __('The AI could not read the post. Try again in a moment.'),
    ];
    $modes = ['onsite' => __('On site'), 'hybrid' => __('Hybrid'), 'remote' => __('Remote')];
    $levels = ['intern' => __('Intern or working student'), 'junior' => __('Junior'), 'mid' => __('Mid-level'), 'senior' => __('Senior'), 'lead' => __('Lead')];
@endphp

<div>
    @if ($post->status === \App\Models\JobPost::READING)
        <section class="card flex items-start gap-4" wire:poll.2s role="status">
            <span class="mt-1 size-5 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
            <div>
                <h1 class="card-title">{{ __('Reading the job post…') }}</h1>
                <p class="muted mt-1 text-sm">{{ __('This takes up to a minute with the local AI. You can leave this page; the post keeps being read.') }}</p>
            </div>
        </section>
    @elseif ($post->status === \App\Models\JobPost::FAILED)
        <section class="card">
            <h1 class="card-title">{{ __('The job post could not be read') }}</h1>
            <p class="alert mt-3">{{ $reasons[$post->error] ?? $reasons['ai'] }}</p>
            @error('retry')
                <p class="field-error mt-2 text-sm" role="alert">{{ $message }}</p>
            @enderror
            <div class="mt-4 flex flex-wrap gap-2">
                @if (! in_array($post->error, ['bad-url', 'blocked-address'], true))
                    <button type="button" wire:click="retry" class="button button-primary">{{ __('Try again') }}</button>
                @endif
                <a href="{{ route('jobs') }}" class="button">{{ __('Paste it instead') }}</a>
                <button type="button" wire:click="delete" class="button button-danger">{{ __('Delete') }}</button>
            </div>
        </section>
    @else
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <section class="card">
                    <h1 class="font-display text-2xl font-semibold wrap-break-word sm:text-3xl">{{ $post->title !== '' ? $post->title : __('Job post') }}</h1>
                    @if ($post->company !== '' || $post->location !== '')
                        <p class="muted mt-1">{{ implode(' · ', array_filter([$post->company, $post->location])) }}</p>
                    @endif
                    <p class="mt-3 flex flex-wrap gap-2">
                        @if ($post->work_mode !== '')
                            <span class="badge">{{ $modes[$post->work_mode] }}</span>
                        @endif
                        @if ($post->seniority !== '')
                            <span class="badge">{{ $levels[$post->seniority] }}</span>
                        @endif
                        <span class="badge">{{ $post->language === 'de' ? __('Post in German') : __('Post in English') }}</span>
                    </p>
                    @if ($post->url !== null)
                        <a href="{{ $post->url }}" class="link mt-3 inline-block text-sm" target="_blank" rel="noopener noreferrer nofollow">{{ __('Open the original post') }} ↗</a>
                    @endif
                    <div class="mt-4">
                        @if ($application !== null)
                            <a href="{{ route('applications.show', ['id' => $application->id]) }}" class="link text-sm" wire:navigate>{{ __('In your applications: :status', ['status' => \App\Models\Application::statusNames()[$application->status]]) }}</a>
                        @else
                            <button type="button" wire:click="track" class="button button-small">{{ __('Track this job') }}</button>
                        @endif
                    </div>
                </section>

                @foreach (['required' => __('Required'), 'nice' => __('Nice to have')] as $list => $heading)
                    <section class="card" aria-labelledby="skills-{{ $list }}">
                        <h2 id="skills-{{ $list }}" class="card-title">{{ $heading }}</h2>
                        @if ($match[$list] === [])
                            <p class="muted mt-2 text-sm">{{ __('The post names none.') }}</p>
                        @else
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($match[$list] as $item)
                                    @if ($item['where'] !== null)
                                        <li class="inline-flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1 text-sm text-brand-900 dark:bg-brand-900 dark:text-brand-100">
                                            <span aria-hidden="true">✓</span>
                                            <span><span class="sr-only">{{ __('You have:') }}</span> {{ $item['skill'] }}</span>
                                            <span class="text-xs opacity-75">{{ $item['where'] === 'skills' ? __('in your skills') : __('in your profile') }}</span>
                                        </li>
                                    @else
                                        <li class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-stone-400 px-3 py-1 text-sm dark:border-stone-600">
                                            <span aria-hidden="true">–</span>
                                            <span><span class="sr-only">{{ __('Missing:') }}</span> {{ $item['skill'] }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach

                <details class="card">
                    <summary class="cursor-pointer font-semibold">{{ __('The post as it was read') }}</summary>
                    <div class="muted mt-3 text-sm whitespace-pre-line wrap-break-word">{{ $post->text }}</div>
                </details>
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <aside class="card flex flex-col gap-4" aria-labelledby="match-title">
                    <h2 id="match-title" class="card-title">{{ __('Your match') }}</h2>
                    @if ($match['score'] === null)
                        <p class="muted text-sm">{{ __('The post names no skills to match against.') }}</p>
                    @else
                        <div class="flex items-center gap-4">
                            <x-jobs.score :score="$match['score']" />
                            <p class="text-sm">
                                {{ __(':have of :total required', ['have' => \App\JobSearch\JobMatch::matched($match['required']), 'total' => count($match['required'])]) }}<br>
                                {{ __(':have of :total nice to have', ['have' => \App\JobSearch\JobMatch::matched($match['nice']), 'total' => count($match['nice'])]) }}
                            </p>
                        </div>
                        <p class="muted text-sm">{{ __('Calculated, not guessed: each skill the post names is looked up in your skills, languages and the rest of your profile. Required skills count three times as much as nice-to-have ones.') }}</p>
                        <a href="{{ route('profile') }}" class="link text-sm">{{ __('Add a missing skill to your profile') }}</a>
                    @endif
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this job post?') }}" class="button button-danger button-small self-start">{{ __('Delete') }}</button>
                </aside>
    
                <section class="card flex flex-col gap-3" aria-labelledby="tailor-title" x-data="{ resume: @js($resumes->whereNull('job_post_id')->first()?->id ?? $resumes->first()?->id) }">
                    <h2 id="tailor-title" class="card-title">{{ __('Tailor a CV') }}</h2>
                    @if ($resumes->isEmpty())
                        <p class="muted text-sm">{{ __('Make a CV first, then tailor a copy of it to this job.') }}</p>
                        <a href="{{ route('resumes') }}" class="button self-start">{{ __('Make a CV') }}</a>
                    @else
                        <p class="muted text-sm">{{ __('Makes a copy of a CV for this job. The AI rewords your summary and bullet points to bring forward what fits, and you accept each change. It never adds a skill you don\'t have.') }}</p>
                        <label class="field">
                            <span>{{ __('Start from') }}</span>
                            <select x-model.number="resume">
                                @foreach ($resumes as $resume)
                                    <option value="{{ $resume->id }}">{{ $resume->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('tailor')
                            <p class="field-error text-sm" role="alert">{{ $message }}</p>
                        @enderror
                        <button type="button" x-on:click="$wire.tailor(resume)" wire:loading.attr="disabled" wire:target="tailor" class="button button-primary self-start">{{ __('Tailor this CV') }}</button>
                    @endif
                    @php($tailored = $resumes->where('job_post_id', $post->id))
                    @if ($tailored->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold">{{ __('Tailored for this job') }}</h3>
                            <ul class="mt-1 text-sm">
                                @foreach ($tailored as $resume)
                                    <li><a href="{{ route('resumes.edit', ['id' => $resume->id]) }}" class="link">{{ $resume->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                @php($withCv = $tailored->first()?->id ?? $resumes->first()?->id)
                <section class="card flex flex-col gap-3" aria-labelledby="letter-title"
                    x-data="{ language: @js($post->language === 'de' ? 'de' : 'en'), tone: 'formal', length: 'standard', resume: @js($withCv) }">
                    <h2 id="letter-title" class="card-title">{{ __('Write a cover letter') }}</h2>
                    <p class="muted text-sm">{{ __('The AI writes it from this job and your profile, and every paragraph is checked against your profile. You can edit it before you download it.') }}</p>
                    <label class="field">
                        <span>{{ __('Language') }}</span>
                        <select x-model="language">
                            <option value="en">{{ __('English') }}</option>
                            <option value="de">{{ __('German (Anschreiben)') }}</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>{{ __('Tone') }}</span>
                        <select x-model="tone">
                            <option value="formal">{{ __('Formal') }}</option>
                            <option value="warm">{{ __('Warm') }}</option>
                            <option value="confident">{{ __('Confident') }}</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>{{ __('Length') }}</span>
                        <select x-model="length">
                            <option value="short">{{ __('Short, about 150 words') }}</option>
                            <option value="standard">{{ __('Standard, about 280 words') }}</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>{{ __('Send with') }}</span>
                        <select x-model.number="resume">
                            <option :value="null">{{ __('No CV') }}</option>
                            @foreach ($resumes as $resume)
                                <option value="{{ $resume->id }}">{{ $resume->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('letter')
                        <p class="field-error text-sm" role="alert">{{ $message }}</p>
                    @enderror
                    <button type="button" x-on:click="$wire.writeLetter(language, tone, length, resume || null)" wire:loading.attr="disabled" wire:target="writeLetter" class="button button-primary self-start">{{ __('Write the letter') }}</button>
                    @if ($letters->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold">{{ __('Letters for this job') }}</h3>
                            <ul class="mt-1 text-sm">
                                @foreach ($letters as $letter)
                                    <li><a href="{{ route('letters.edit', ['id' => $letter->id]) }}" class="link">{{ $letter->language === 'de' ? __('Cover letter (German)') : __('Cover letter (English)') }} · {{ $letter->updated_at->translatedFormat('j M Y, H:i') }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @endif
</div>
