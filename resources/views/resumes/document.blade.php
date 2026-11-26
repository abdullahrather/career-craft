@php
    /** @var \App\Resumes\ResumeDocument $doc */
    $look = $doc->look;
    $photo = $doc->photo();
    $template = $doc->resume->template;
@endphp
<!DOCTYPE html>
<html lang="{{ $doc->resume->language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->name() }} · {{ $doc->resume->name }}</title>
    {{-- The PDF service gets the font files next to this page, so it needs nothing else. --}}
    <style>
        @foreach ($doc->fontFiles() as $weight => $file)
            @font-face { font-family: '{{ \App\Models\Resume::FONT_NAMES[$look['font']] }}'; font-weight: {{ $weight }}; src: url('{{ $file }}') format('woff2'); }
        @endforeach
    </style>
    <style>
        /* A4, the same on screen and on paper. One page uses a smaller size and tighter spacing. */
        @page { size: A4; margin: 16mm 18mm; }
        :root {
            --accent: {{ $look['accent'] }};
            --font: {!! \App\Models\Resume::FONTS[$look['font']] !!};
            --size: {{ $look['pages'] === 1 ? '9.5pt' : '10.5pt' }};
            --space: {{ $look['pages'] === 1 ? '3.4mm' : '5mm' }};
        }
        * { box-sizing: border-box; }
        html { background: #fff; }
        body { margin: 0; color: #1c1917; font-family: var(--font); font-size: var(--size); line-height: 1.45; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .page { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 16mm 18mm; background: #fff; }
        h1, h2, h3, p, ul { margin: 0; }
        h1, h2, h3 { font-weight: 600; line-height: 1.2; }
        a { color: inherit; text-decoration: none; }
        ul { padding-left: 1.1em; }
        h2 { break-after: avoid; }
        .entry, .row { break-inside: avoid; }
        .muted { color: #57534e; }
        .photo { width: 30mm; height: 40mm; flex-shrink: 0; object-fit: cover; border-radius: 1.5mm; }
        .section { margin-top: var(--space); }
        .entry + .entry { margin-top: calc(var(--space) * 0.6); }
        .entry-head { display: flex; justify-content: space-between; align-items: baseline; gap: 4mm; }
        .entry h3 { font-size: 1.05em; }
        .when { flex-shrink: 0; white-space: nowrap; color: #57534e; font-size: 0.92em; }
        .entry p + p, .entry p + ul { margin-top: 1mm; }
        .entry li + li { margin-top: 0.4mm; }
        @media print {
            .page { width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div id="content">
            @include('resumes.templates.'.$template, ['doc' => $doc, 'photo' => $photo])
        </div>
    </main>
</body>
</html>
