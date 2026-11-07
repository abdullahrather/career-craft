{{-- Classic: a centred header and ruled section headings. Calm and traditional. --}}
<style>
    .classic header { position: relative; text-align: center; padding-bottom: var(--space); border-bottom: 0.8pt solid var(--accent); }
    .classic header.has-photo { min-height: 40mm; padding-left: 34mm; padding-right: 34mm; }
    .classic header .photo { position: absolute; top: 0; right: 0; }
    .classic h1 { font-size: 2.3em; color: var(--accent); letter-spacing: 0.01em; }
    .classic .headline { margin-top: 1.2mm; font-size: 1.15em; }
    .classic .contact { margin-top: 1.5mm; color: #57534e; }
    .classic h2 { margin-bottom: 2mm; padding-bottom: 0.8mm; border-bottom: 0.4pt solid #d6d3d1; color: var(--accent); font-size: 0.92em; letter-spacing: 0.12em; text-transform: uppercase; }
</style>
<div class="classic">
    <header @class(['has-photo' => $photo])>
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @endif
        <h1>{{ $doc->name() }}</h1>
        @if ($doc->profile->headline !== '')
            <p class="headline">{{ $doc->profile->headline }}</p>
        @endif
        <p class="contact">{{ implode(' · ', $doc->contact()) }}</p>
    </header>
    @include('resumes.partials.sections')
</div>
