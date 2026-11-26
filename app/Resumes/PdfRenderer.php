<?php

declare(strict_types=1);

namespace App\Resumes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Turns a CV or letter page into a PDF with Gotenberg (headless Chromium), using the same HTML as the preview. */
final class PdfRenderer
{
    /**
     * @return string The PDF.
     *
     * @throws ConnectionException|RequestException When Gotenberg isn't running or fails.
     */
    public function render(ResumeDocument $doc): string
    {
        return $this->renderHtml($doc->render(), $doc->fontFiles(), $doc->title(), $doc->name());
    }

    /**
     * @param  array<int, string>  $fontFiles  The font files the page loads, from resources/fonts.
     * @return string The PDF.
     *
     * @throws ConnectionException|RequestException When Gotenberg isn't running or fails.
     */
    public function renderHtml(string $html, array $fontFiles, string $title, string $author): string
    {
        $request = Http::timeout(60)->asMultipart()->attach('files', $html, 'index.html');
        foreach ($fontFiles as $file) {
            $request->attach('files', (string) file_get_contents(resource_path("fonts/{$file}")), $file);
        }

        return $request->post(config()->string('services.gotenberg.url').'/forms/chromium/convert/html', [
            // The page's own A4 size and margins (@page) decide the layout.
            'preferCssPageSize' => 'true',
            'printBackground' => 'true',
            'marginTop' => '0',
            'marginBottom' => '0',
            'marginLeft' => '0',
            'marginRight' => '0',
            'waitForExpression' => "document.fonts.status === 'loaded'",
            'metadata' => (string) json_encode(['Title' => $title, 'Author' => $author]),
        ])->throw()->body();
    }

    /**
     * Joins PDFs into one, in the order given: a letter, then its CV.
     *
     * @param  list<string>  $pdfs
     *
     * @throws ConnectionException|RequestException When Gotenberg isn't running or fails.
     */
    public function merge(array $pdfs): string
    {
        $request = Http::timeout(60)->asMultipart();
        foreach ($pdfs as $index => $pdf) {
            // Gotenberg joins the files in the alphabetical order of their names.
            $request->attach('files', $pdf, sprintf('%02d.pdf', $index));
        }

        return $request->post(config()->string('services.gotenberg.url').'/forms/pdfengines/merge')->throw()->body();
    }
}
