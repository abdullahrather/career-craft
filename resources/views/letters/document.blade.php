@php
    /** @var \App\Letters\LetterDocument $doc */
@endphp
<!DOCTYPE html>
<html lang="{{ $doc->letter->language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->title() }}</title>
    @if ($pdf)
        {{-- The PDF service gets the font files next to this page, so it needs nothing else. --}}
        <style>
            @foreach ($doc->fontFiles() as $weight => $file)
                @font-face { font-family: '{{ \App\Models\Resume::FONT_NAMES[$doc->font] }}'; font-weight: {{ $weight }}; src: url('{{ $file }}') format('woff2'); }
            @endforeach
        </style>
    @else
        @fonts([$doc->font])
    @endif
    <style>
        /* DIN 5008, form B: 25 mm left and 20 mm right, the address field 45 mm from the top. */
        @page { size: A4; margin: 20mm 20mm 20mm 25mm; }
        :root { --accent: {{ $doc->accent }}; --font: {!! \App\Models\Resume::FONTS[$doc->font] !!}; }
        * { box-sizing: border-box; }
        html { background: #fff; }
        body { margin: 0; color: #1c1917; font-family: var(--font); font-size: 11pt; line-height: 1.5; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm 20mm 20mm 25mm; background: #fff; }
        p { margin: 0; }
        .sender { height: 25mm; }
        .sender h1 { margin: 0; color: var(--accent); font-size: 16pt; font-weight: 600; line-height: 1.2; }
        .sender p { margin-top: 1mm; color: #57534e; font-size: 9pt; }
        .address { width: 85mm; height: 45mm; }
        .return { padding-top: 2mm; height: 5mm; color: #57534e; font-size: 7pt; white-space: nowrap; overflow: hidden; }
        .recipient { padding-top: 3mm; }
        .date { margin-top: 8mm; text-align: right; }
        .subject { margin-top: 8mm; font-weight: 600; }
        .greeting { margin-top: 8mm; }
        .body p { margin-top: 4mm; }
        .closing { margin-top: 6mm; break-inside: avoid; }
        .signature { margin-top: 14mm; }
        .enclosure { margin-top: 8mm; font-size: 9pt; color: #57534e; }
        @media print { .page { width: auto; min-height: 0; padding: 0; } }
    </style>
</head>
<body>
    <main class="page">
        <header class="sender">
            <h1>{{ $doc->name() }}</h1>
            @if ($doc->contact() !== [])
                <p>{{ implode(' · ', $doc->contact()) }}</p>
            @endif
        </header>

        <div class="address">
            <p class="return">{{ implode(' · ', array_filter([$doc->name(), $doc->profile->city])) }}</p>
            <div class="recipient">
                @foreach ($doc->recipient() as $line)
                    <p>{{ $line }}</p>
                @endforeach
            </div>
        </div>

        <p class="date">{{ $doc->dateLine() }}</p>
        @if ($doc->letter->subject !== '')
            <p class="subject">{{ $doc->letter->subject }}</p>
        @endif

        <p class="greeting">{{ $doc->greeting() }}</p>
        <div class="body">
            @foreach ($doc->paragraphs() as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="closing">
            <p>{{ $doc->closing() }}</p>
            <p class="signature">{{ $doc->name() }}</p>
        </div>

        @if ($doc->enclosure() !== null)
            <p class="enclosure">{{ $doc->enclosure() }}</p>
        @endif
    </main>
</body>
</html>
