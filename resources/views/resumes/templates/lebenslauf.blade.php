{{-- Lebenslauf: the German tabular CV, with dates on the left, personal details, an optional photo and a signature line. --}}
@php
    use App\Livewire\Profile\Languages;
    use App\Livewire\Profile\Skills;
@endphp
<style>
    .lebenslauf header { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
    .lebenslauf .doc-title { color: var(--accent); font-size: 0.9em; letter-spacing: 0.2em; text-transform: uppercase; }
    .lebenslauf h1 { margin-top: 1mm; font-size: 2.1em; }
    .lebenslauf .headline { margin-top: 1mm; font-size: 1.1em; }
    .lebenslauf h2 { margin-bottom: 2mm; padding-bottom: 0.8mm; border-bottom: 0.6pt solid var(--accent); color: var(--accent); font-size: 1.1em; }
    .lebenslauf .row { display: grid; grid-template-columns: 34mm 1fr; gap: 5mm; }
    .lebenslauf .row + .row { margin-top: calc(var(--space) * 0.5); }
    .lebenslauf .label { color: #57534e; }
    .lebenslauf .row ul { margin-top: 1mm; }
    .lebenslauf .signature { margin-top: calc(var(--space) * 2); break-inside: avoid; }
    .lebenslauf .signature-line { width: 65mm; margin-top: 14mm; padding-top: 1mm; border-top: 0.6pt solid #1c1917; }
</style>
<div class="lebenslauf">
    <header>
        <div>
            <p class="doc-title">{{ __('Curriculum vitae') }}</p>
            <h1>{{ $doc->name() }}</h1>
            @if ($doc->profile->headline !== '')
                <p class="headline">{{ $doc->profile->headline }}</p>
            @endif
        </div>
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @endif
    </header>

    <section class="section">
        <h2>{{ __('Personal details') }}</h2>
        @foreach (array_filter([__('Place of residence') => $doc->place(), __('Phone') => $doc->profile->phone, __('Email') => $doc->profile->email]) as $label => $value)
            <div class="row"><span class="label">{{ $label }}</span><span>{{ $value }}</span></div>
        @endforeach
    </section>

    @foreach ($doc->sections() as $section)
        <section class="section section-{{ $section['key'] }}">
            <h2>{{ $section['title'] }}</h2>
            @switch($section['key'])
                @case('summary')
                    <p>{!! nl2br(e($section['content'])) !!}</p>
                    @break
                @case('experiences')
                    @foreach ($section['content'] as $job)
                        <div class="row">
                            <span class="label">{{ $doc->period($job->starts_on, $job->ends_on) }}</span>
                            <div>
                                <h3>{{ $job->title }}</h3>
                                <p>{{ implode(', ', array_filter([$job->employer, $job->city])) }}</p>
                                @if ($job->description)
                                    <p>{{ $job->description }}</p>
                                @endif
                                @if ($job->achievements !== [])
                                    <ul>
                                        @foreach ($job->achievements as $achievement)
                                            <li>{{ $achievement }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @break
                @case('educations')
                    @foreach ($section['content'] as $education)
                        <div class="row">
                            <span class="label">{{ $doc->period($education->starts_on, $education->ends_on) }}</span>
                            <div>
                                <h3>{{ implode(', ', array_filter([$education->degree, $education->field])) }}</h3>
                                <p>{{ implode(', ', array_filter([$education->institution, $education->city])) }}</p>
                                @if ($education->grade !== '')
                                    <p>{{ __('Grade: :grade', ['grade' => $education->grade]) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @break
                @case('skills')
                    {{-- Grouped by level, best first, as German CVs usually list them. --}}
                    @foreach ($section['content']->sortByDesc('level')->groupBy('level') as $level => $skills)
                        <div class="row">
                            <span class="label">{{ Skills::levels()[$level] ?? '' }}</span>
                            <span>{{ $skills->pluck('name')->implode(', ') }}</span>
                        </div>
                    @endforeach
                    @break
                @case('languages')
                    @foreach ($section['content'] as $language)
                        <div class="row"><span class="label">{{ $language->name }}</span><span>{{ Languages::levels()[$language->level] ?? $language->level }}</span></div>
                    @endforeach
                    @break
                @case('projects')
                    @foreach ($section['content'] as $project)
                        <div class="row">
                            <span class="label">{{ $doc->period($project->starts_on, $project->ends_on, ongoing: false) }}</span>
                            <div>
                                <h3>{{ implode(', ', array_filter([$project->name, $project->role])) }}</h3>
                                @if ($project->description)
                                    <p>{{ $project->description }}</p>
                                @endif
                                @if ($project->url !== '')
                                    <p class="muted">{{ $project->url }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @break
                @case('certifications')
                    @foreach ($section['content'] as $certificate)
                        <div class="row">
                            <span class="label">{{ $doc->month($certificate->issued_on) }}</span>
                            <span>{{ implode(', ', array_filter([$certificate->name, $certificate->issuer])) }}</span>
                        </div>
                    @endforeach
                    @break
                @default
                    @foreach ($section['content'] as $link)
                        <div class="row"><span class="label">{{ $link->label }}</span><a href="{{ $link->url }}">{{ $link->url }}</a></div>
                    @endforeach
            @endswitch
        </section>
    @endforeach

    @if ($doc->look['signature'])
        <div class="signature">
            <p>{{ $doc->signedAt() }}</p>
            <p class="signature-line">{{ $doc->name() }}</p>
        </div>
    @endif
</div>
