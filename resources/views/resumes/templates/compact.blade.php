{{-- Compact: tight spacing and a one-line header, to fit a long career on fewer pages. --}}
<style>
    .compact { --space: calc({{ $doc->look['pages'] === 1 ? '3.4mm' : '5mm' }} * 0.7); }
    .compact header { display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; padding-bottom: 2mm; border-bottom: 1.2pt solid var(--accent); }
    .compact h1 { font-size: 1.9em; }
    .compact .headline { margin-top: 0.5mm; color: var(--accent); font-weight: 600; }
    .compact .contact { text-align: right; color: #57534e; font-size: 0.92em; }
    .compact .photo { width: 22mm; height: 29mm; }
    .compact h2 { margin-bottom: 1.2mm; color: var(--accent); font-size: 0.85em; letter-spacing: 0.1em; text-transform: uppercase; }
    .compact .entry-head h3 { font-size: 1em; }
    .compact .where { color: #44403c; }
</style>
<div class="compact">
    <header>
        <div>
            <h1>{{ $doc->name() }}</h1>
            @if ($doc->profile->headline !== '')
                <p class="headline">{{ $doc->profile->headline }}</p>
            @endif
        </div>
        <div class="contact">
            @foreach ($doc->contact() as $item)
                <p>{{ $item }}</p>
            @endforeach
        </div>
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @endif
    </header>
    @include('resumes.partials.sections')
</div>
