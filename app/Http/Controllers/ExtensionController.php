<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplyQueueItem;
use App\Models\ProfileLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the browser extension reads to fill an application form: the person's details, and
 * for the job page they're on, the tracked application with the CV and letter recorded for
 * it. Reading only; the person submits the form themselves.
 */
final class ExtensionController
{
    public function profile(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $profile = $user->careerProfile()->load('links');
        $name = preg_split('/\s+/u', trim($user->name), 2) ?: [];
        $link = fn (string $host): ?string => $profile->links->first(fn (ProfileLink $link): bool => Str::contains(Str::lower((string) parse_url($link->url, PHP_URL_HOST)), $host))?->url;

        return response()->json([
            'first_name' => $name[0] ?? '',
            'last_name' => $name[1] ?? '',
            'email' => $profile->email !== '' ? $profile->email : $user->email,
            'phone' => $profile->phone,
            'city' => $profile->city,
            'country' => $profile->country,
            'headline' => $profile->headline,
            'linkedin' => $link('linkedin.com'),
            'github' => $link('github.com'),
            'website' => $profile->links->first(fn (ProfileLink $link): bool => ! Str::contains(Str::lower($link->url), ['linkedin.com', 'github.com']))?->url,
            'salary_expectation' => $profile->salary_expectation,
            'notice_period' => $profile->notice_period,
            'earliest_start' => $profile->earliest_start?->toDateString(),
            'work_permit' => $profile->work_permit,
        ]);
    }

    /** The tracked application for the job page the person is on, if there is one. */
    public function application(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $wanted = Application::normaliseUrl($request->string('url')->toString());
        $matches = $wanted === null ? collect() : $user->applications()->whereNotNull('url')->get()
            ->filter(fn (Application $application): bool => Application::normaliseUrl((string) $application->url) === $wanted);
        // Rather no documents than another job's.
        if ($matches->count() > 1) {
            return response()->json(['message' => __('Several of your applications are for this page. Keep one, then fill the form.')], 409);
        }
        $application = $matches->first();
        if ($application === null) {
            return response()->json(['message' => __('This job isn\'t in your applications. Track it on CareerCraft first.')], 404);
        }
        // Only answers the fact guard checked and found nothing new in: the extension pastes them
        // without showing the warnings the review page shows.
        $answers = array_filter(
            ApplyQueueItem::query()->where('application_id', $application->id)->latest('id')->first()->answers ?? [],
            fn (array $answer): bool => $answer['checked'] && $answer['flags'] === [],
        );

        return response()->json([
            'id' => $application->id,
            'title' => $application->title,
            'company' => $application->company,
            'documents' => array_filter([
                'cv' => $application->cv_path !== null ? ['name' => (Str::slug((string) $application->cv_name) ?: 'cv').'.pdf', 'url' => route('extension.document', ['id' => $application->id, 'document' => 'cv'])] : null,
                'letter' => $application->letter_path !== null ? ['name' => (Str::slug((string) $application->letter_name) ?: 'letter').'.pdf', 'url' => route('extension.document', ['id' => $application->id, 'document' => 'letter'])] : null,
            ]),
            'answers' => array_values(array_map(fn (array $answer): array => ['question' => $answer['question'], 'text' => $answer['text']], $answers)),
        ]);
    }

    /** A recorded CV or letter, to put in the form's file field. */
    public function document(Request $request, int $id, string $document): StreamedResponse
    {
        $application = $this->user($request)->applications()->findOrFail($id);
        $path = $document === 'cv' ? $application->cv_path : $application->letter_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'application/pdf']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->tokenCan('extension'), 403);

        return $user;
    }
}
