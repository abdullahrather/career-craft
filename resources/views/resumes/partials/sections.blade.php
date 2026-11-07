{{-- The sections of the single-column templates. Plain text in reading order, so applicant tracking systems read it as written. --}}
@foreach ($doc->sections() as $section)
    <section class="section section-{{ $section['key'] }}">
        <h2>{{ $section['title'] }}</h2>
        @switch($section['key'])
            @case('summary')
                <p class="summary">{!! nl2br(e($section['content'])) !!}</p>
                @break
            @case('experiences')
                @foreach ($section['content'] as $job)
                    <div class="entry">
                        <div class="entry-head">
                            <h3>{{ $job->title }}</h3>
                            <span class="when">{{ $doc->period($job->starts_on, $job->ends_on) }}</span>
                        </div>
                        <p class="where">{{ implode(', ', array_filter([$job->employer, $job->city])) }}</p>
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
                @endforeach
                @break
            @case('educations')
                @foreach ($section['content'] as $education)
                    <div class="entry">
                        <div class="entry-head">
                            <h3>{{ implode(', ', array_filter([$education->degree, $education->field])) }}</h3>
                            <span class="when">{{ $doc->period($education->starts_on, $education->ends_on) }}</span>
                        </div>
                        <p class="where">{{ implode(', ', array_filter([$education->institution, $education->city])) }}</p>
                        @if ($education->grade !== '')
                            <p class="muted">{{ __('Grade: :grade', ['grade' => $education->grade]) }}</p>
                        @endif
                        @if ($education->description)
                            <p>{{ $education->description }}</p>
                        @endif
                    </div>
                @endforeach
                @break
            @case('skills')
                <p>{{ $section['content']->pluck('name')->implode(', ') }}</p>
                @break
            @case('languages')
                <p>{{ $section['content']->map(fn ($language) => $language->name.' ('.($language->level === 'native' ? __('Native speaker') : $language->level).')')->implode(', ') }}</p>
                @break
            @case('projects')
                @foreach ($section['content'] as $project)
                    <div class="entry">
                        <div class="entry-head">
                            <h3>{{ implode(', ', array_filter([$project->name, $project->role])) }}</h3>
                            <span class="when">{{ $doc->period($project->starts_on, $project->ends_on, ongoing: false) }}</span>
                        </div>
                        @if ($project->url !== '')
                            <p class="muted"><a href="{{ $project->url }}">{{ $project->url }}</a></p>
                        @endif
                        @if ($project->description)
                            <p>{{ $project->description }}</p>
                        @endif
                        @if ($project->achievements !== [])
                            <ul>
                                @foreach ($project->achievements as $achievement)
                                    <li>{{ $achievement }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
                @break
            @case('certifications')
                <ul>
                    @foreach ($section['content'] as $certificate)
                        <li>{{ implode(', ', array_filter([$certificate->name, $certificate->issuer, $doc->month($certificate->issued_on)])) }}</li>
                    @endforeach
                </ul>
                @break
            @default
                <ul>
                    @foreach ($section['content'] as $link)
                        <li>{{ $link->label }}: <a href="{{ $link->url }}">{{ $link->url }}</a></li>
                    @endforeach
                </ul>
        @endswitch
    </section>
@endforeach
