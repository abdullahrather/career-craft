<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\User;
use App\Resumes\ResumeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    private function find(Request $request, int $id): Resume
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user->resumes()->findOrFail($id);
    }
}
