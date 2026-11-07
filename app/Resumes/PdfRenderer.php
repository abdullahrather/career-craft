<?php

declare(strict_types=1);

namespace App\Resumes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Turns a CV page into a PDF with Gotenberg (headless Chromium), using the same HTML as the preview. */
final class PdfRenderer
{
    /**
     * @return string The PDF.
     *
     * @throws ConnectionException|RequestException When Gotenberg isn't running or fails.
     */
    public function render(ResumeDocument $doc): string
    {
        $request = Http::timeout(60)->asMultipart()->attach('files', $doc->render(pdf: true), 'index.html');
        foreach ($doc->fontFiles() as $file) {
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
            'metadata' => (string) json_encode(['Title' => $doc->title(), 'Author' => $doc->name()]),
        ])->throw()->body();
    }
}
