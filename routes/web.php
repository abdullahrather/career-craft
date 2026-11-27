<?php

declare(strict_types=1);

use App\Http\Controllers\GmailConnectController;
use App\Http\Controllers\GmailMailboxController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OutlookConnectController;
use App\Http\Controllers\OutlookMailboxController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\SetPasswordController;
use App\Http\Controllers\SocialLoginController;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::view('/', 'home')->name('home');

Route::post('/locale', LocaleController::class)->name('locale');

// Sign in with Google or GitHub, or connect one from the settings page.
Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    // Sending applications through the candidate's Gmail or Outlook, with their permission.
    Route::get('/settings/mail/gmail', [GmailConnectController::class, 'redirect'])->name('mail.gmail');
    Route::get('/settings/mail/gmail/callback', [GmailConnectController::class, 'callback'])->name('mail.gmail.callback');
    Route::get('/settings/mail/outlook', [OutlookConnectController::class, 'redirect'])->name('mail.outlook');
    Route::get('/settings/mail/outlook/callback', [OutlookConnectController::class, 'callback'])->name('mail.outlook.callback');
    // Reading the candidate's Gmail or Outlook for employers' replies, with read-only permission.
    Route::get('/settings/mailbox/gmail', [GmailMailboxController::class, 'redirect'])->name('mailbox.gmail');
    Route::get('/settings/mailbox/gmail/callback', [GmailMailboxController::class, 'callback'])->name('mailbox.gmail.callback');
    Route::get('/settings/mailbox/outlook', [OutlookMailboxController::class, 'redirect'])->name('mailbox.outlook');
    Route::get('/settings/mailbox/outlook/callback', [OutlookMailboxController::class, 'callback'])->name('mailbox.outlook.callback');
    Route::view('/profile', 'profile')->name('profile');
    Route::view('/profile/import', 'profile-import')->name('profile.import');

    Route::view('/resumes', 'resumes.index')->name('resumes');
    Route::get('/resumes/{id}', [ResumeController::class, 'edit'])->whereNumber('id')->name('resumes.edit');
    Route::get('/resumes/{id}/preview', [ResumeController::class, 'preview'])->whereNumber('id')->name('resumes.preview');
    Route::view('/resumes/compare', 'resumes.compare')->name('resumes.compare');
    Route::get('/resume-versions/{id}/{format}', [ResumeController::class, 'download'])->whereNumber('id')->whereIn('format', ['pdf', 'docx'])->name('resume-versions.download');

    Route::view('/jobs', 'jobs.index')->name('jobs');
    Route::get('/jobs/{id}', function (int $id): View {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('jobs.show', ['post' => $user->jobPosts()->findOrFail($id)]);
    })->whereNumber('id')->name('jobs.show');

    Route::view('/discover', 'discover')->name('discover');

    Route::view('/apply-queue', 'apply-queue.index')->name('apply-queue');
    Route::get('/apply-queue/{id}', function (int $id): View {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('apply-queue.review', ['item' => $user->applyQueueItems()->findOrFail($id)]);
    })->whereNumber('id')->name('apply-queue.review');

    Route::view('/applications', 'applications.index')->name('applications');
    Route::get('/applications/{id}', function (int $id): View {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('applications.show', ['application' => $user->applications()->findOrFail($id)]);
    })->whereNumber('id')->name('applications.show');
    // The copies of the CV and letter sent; only their owner gets them.
    Route::get('/applications/{id}/{document}', function (int $id, string $document): StreamedResponse {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $application = $user->applications()->findOrFail($id);
        $path = $document === 'cv' ? $application->cv_path : $application->letter_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);
        $name = $document === 'cv' ? $application->cv_name : $application->letter_name;

        return Storage::disk('local')->download($path, (Str::slug((string) $name) ?: $document).'.pdf');
    })->whereNumber('id')->whereIn('document', ['cv', 'letter'])->name('applications.download');
    Route::get('/applications/{id}/interviews/{interview}.ics', function (int $id, int $interview): Response {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $found = $user->applications()->findOrFail($id)->interviews()->findOrFail($interview);

        return response($found->ics(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="interview-'.$found->starts_at->format('Y-m-d').'.ics"',
        ]);
    })->whereNumber(['id', 'interview'])->name('applications.interview');

    Route::get('/letters/{id}', [LetterController::class, 'edit'])->whereNumber('id')->name('letters.edit');
    Route::get('/letters/{id}/preview', [LetterController::class, 'preview'])->whereNumber('id')->name('letters.preview');
    Route::get('/letters/{id}/pdf/{with}', [LetterController::class, 'download'])->whereNumber('id')->whereIn('with', ['letter', 'cv'])->name('letters.download');

    Route::view('/admin/ai', 'admin.ai')->middleware('can:admin')->name('admin.ai');

    // The CV photo is private: only its owner sees it.
    Route::get('/profile/photo', function (): StreamedResponse {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $path = $user->careerProfile()->photo_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
    })->name('profile.photo');

    Route::post('/settings/password', SetPasswordController::class)->name('password.set');

    // Only while another way in remains: a password or another connected account.
    Route::delete('/settings/connections/{provider}', function (string $provider): RedirectResponse {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        if ($user->password === null && $user->socialAccounts()->where('provider', '!=', $provider)->doesntExist()) {
            return back()->with('error', __('Set a password first, so you can still sign in.'));
        }
        $user->socialAccounts()->where('provider', $provider)->delete();

        return back()->with('status', __('Disconnected.'));
    })->name('social.disconnect');
});
