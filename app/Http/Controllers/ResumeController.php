<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Resumes\ResumeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The CV editor page and the CV itself. CVs are looked up among the person's own, so anyone else's isn't found. */
final class ResumeController
{
    public function edit(Request $request, int $id): View
    {
        return view('resumes.edit', ['resume' => $this->find($request, $id)]);
    }

    public function preview(Request $request, int $id): Response
    {
        return response(ResumeDocument::for($this->find($request, $id))->render(preview: true))
            ->header('Cache-Control', 'no-store');
    }

    /** A saved version's PDF or Word file. */
    public function download(Request $request, int $id, string $format): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $version = ResumeVersion::query()
            ->whereHas('resume', fn (Builder $query) => $query->where('user_id', $user->id))
            ->findOrFail($id);
        $path = $format === 'pdf' ? $version->pdf_path : $version->docx_path;

        return Storage::disk('local')->download($path, $version->filename($format));
    }

    private function find(Request $request, int $id): Resume
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user->resumes()->findOrFail($id);
    }
}
