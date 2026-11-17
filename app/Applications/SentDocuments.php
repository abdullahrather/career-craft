<?php

declare(strict_types=1);

namespace App\Applications;

use App\Letters\LetterDocument;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Resumes\PdfRenderer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps a copy of the CV version and letter sent with an application, so it shows exactly
 * what went out even after the CV is deleted or the letter edited.
 */
final readonly class SentDocuments
{
    public function __construct(private PdfRenderer $pdf) {}

    /**
     * Records the documents, or none for either. Both PDFs are in hand before anything is
     * written, so a failure leaves the copies recorded before as they were.
     *
     * @return array{field: string, message: string}|null What went wrong, if anything.
     */
    public function record(Application $application, ?ResumeVersion $version, ?CoverLetter $letter): ?array
    {
        if ($letter !== null && ! $letter->sendable()) {
            return ['field' => 'letter', 'message' => __('Open this letter and confirm or remove what is marked first.')];
        }
        $cv = $version !== null ? Storage::disk('local')->get($version->pdf_path) : null;
        if ($version !== null && $cv === null) {
            return ['field' => 'cvVersion', 'message' => __('The PDF of this CV version is missing. Save the CV again in the CV editor.')];
        }
        $letterPdf = null;
        $letterName = null;
        if ($letter !== null) {
            $doc = new LetterDocument($letter);
            $letterName = $letter->subject !== '' ? $letter->subject : $doc->title();
            try {
                $letterPdf = $this->pdf->renderHtml($doc->render(pdf: true), $doc->fontFiles(), $doc->title(), $doc->name());
            } catch (ConnectionException|RequestException $exception) {
                report($exception);

                return ['field' => 'letter', 'message' => __('The PDF could not be made. Check that the PDF service is running: docker compose up -d gotenberg')];
            }
        }

        $hadDocuments = $application->cv_path !== null || $application->letter_path !== null;
        $directory = Application::directory($application->id);
        $disk = Storage::disk('local');
        $cv !== null ? $disk->put("{$directory}/cv.pdf", $cv) : $disk->delete("{$directory}/cv.pdf");
        $letterPdf !== null ? $disk->put("{$directory}/letter.pdf", $letterPdf) : $disk->delete("{$directory}/letter.pdf");
        $application->update([
            'resume_version_id' => $version?->id,
            'cv_name' => $version !== null ? __(':name, version :number', ['name' => $version->name, 'number' => $version->number]) : null,
            'cv_path' => $cv !== null ? "{$directory}/cv.pdf" : null,
            'cover_letter_id' => $letter?->id,
            'letter_name' => $letterName,
            'letter_path' => $letterPdf !== null ? "{$directory}/letter.pdf" : null,
        ]);
        if ($cv !== null || $letterPdf !== null || $hadDocuments) {
            $application->log('documents', ['cv' => $application->cv_name, 'letter' => $letterName]);
        }

        return null;
    }
}
