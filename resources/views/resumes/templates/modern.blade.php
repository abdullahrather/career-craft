{{-- Modern: the name and headings in the accent colour, with a bar beside each heading. --}}
<style>
    .modern header { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
    .modern h1 { font-size: 2.4em; color: var(--accent); letter-spacing: -0.01em; }
    .modern .headline { margin-top: 1mm; font-size: 1.2em; font-weight: 600; }
    .modern .contact { margin-top: 2mm; color: #57534e; }
    .modern .contact span + span::before { content: '|'; margin: 0 2mm; color: #a8a29e; }
    .modern h2 { margin-bottom: 2mm; padding-left: 2.5mm; border-left: 1.2mm solid var(--accent); color: var(--accent); font-size: 1.1em; }
    .modern .where { font-weight: 600; color: #44403c; }
</style>
<div class="modern">
    <header>
        <div>
            <h1>{{ $doc->name() }}</h1>
            @if ($doc->profile->headline !== '')
                <p class="headline">{{ $doc->profile->headline }}</p>
            @endif
            <p class="contact">
                @foreach ($doc->contact() as $item)
                    <span>{{ $item }}</span>
                @endforeach
            </p>
        </div>
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @endif
    </header>
    @include('resumes.partials.sections')
</div>
