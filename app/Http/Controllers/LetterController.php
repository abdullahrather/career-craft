<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Letters\LetterDocument;
use App\Models\CoverLetter;
use App\Models\User;
use App\Resumes\PdfRenderer;
use App\Resumes\ResumeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** The letter page, its preview and its PDF. Letters are looked up among the person's own, so anyone else's isn't found. */
final class LetterController
{
    public function edit(Request $request, int $id): View
    {
        return view('letters.edit', ['letter' => $this->find($request, $id)]);
    }

    public function preview(Request $request, int $id): Response
    {
        return response((new LetterDocument($this->find($request, $id)))->render())->header('Cache-Control', 'no-store');
    }

    /** The letter as a PDF, alone or followed by its CV. Only once nothing flagged is left to confirm. */
    public function download(Request $request, PdfRenderer $pdf, int $id, string $with): Response|RedirectResponse
    {
        $letter = $this->find($request, $id);
        abort_unless($letter->sendable(), 403);
        abort_if($with === 'cv' && $letter->resume === null, 404);
        $doc = new LetterDocument($letter);

        try {
            $file = $pdf->renderHtml($doc->render(pdf: true), $doc->fontFiles(), $doc->title(), $doc->name());
            if ($with === 'cv') {
                $file = $pdf->merge([$file, $pdf->render(ResumeDocument::for($letter->resume))]);
            }
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return back()->with('error', __('The PDF could not be made. Check that the PDF service is running: docker compose up -d gotenberg'));
        }

        $german = $letter->language === 'de';
        $name = Str::slug($doc->name()).'-'.($with === 'cv' ? ($german ? 'Bewerbung' : 'Application') : ($german ? 'Anschreiben' : 'Cover-letter')).'.pdf';

        return response($file, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }

    private function find(Request $request, int $id): CoverLetter
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user->coverLetters()->findOrFail($id);
    }
}
