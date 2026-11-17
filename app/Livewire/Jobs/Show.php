<?php

declare(strict_types=1);

namespace App\Livewire\Jobs;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\Jobs\WriteCoverLetter;
use App\JobSearch\JobMatch;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\JobPost;
use App\Models\Resume;
use App\Models\User;
use App\Resumes\Tailoring;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One job post: what was read from it, and how the profile matches it. Polled while the
 * post is being read. The score is worked out again on every view, so a change to the
 * profile shows up straight away.
 */
final class Show extends Component
{
    #[Locked]
    public int $postId;

    public function mount(int $id): void
    {
        $this->postId = $this->post($id)->id;
    }

    /** Reads the post again, after a failure. */
    public function retry(AiRunner $ai): void
    {
        $post = $this->post($this->postId);
        abort_unless($post->status === JobPost::FAILED, 404);
        if ($ai->remaining($post->user) === 0) {
            $this->addError('retry', (new AiLimitReached)->getMessage());

            return;
        }
        $post->update(['status' => JobPost::READING, 'error' => null]);
        AnalyseJobPost::dispatch($post);
    }

    /** Makes a copy of one of the person's CVs for this job, and has the AI reword it. */
    public function tailor(AiRunner $ai, int $resumeId): void
    {
        $post = $this->post($this->postId);
        abort_unless($post->status === JobPost::READY, 404);
        $base = $post->user->resumes()->findOrFail($resumeId);
        if ($ai->remaining($post->user) === 0) {
            $this->addError('tailor', (new AiLimitReached)->getMessage());

            return;
        }

        $this->redirectRoute('resumes.edit', ['id' => Tailoring::start($base, $post)->id]);
    }

    /** Has the AI write a cover letter for this job, optionally sent with one of the person's CVs. */
    public function writeLetter(AiRunner $ai, string $language, string $tone, string $length, ?int $resumeId = null): void
    {
        $post = $this->post($this->postId);
        abort_unless($post->status === JobPost::READY, 404);
        abort_unless(in_array($language, Resume::LANGUAGES, true) && in_array($tone, CoverLetter::TONES, true) && in_array($length, CoverLetter::LENGTHS, true), 404);
        $resume = $resumeId !== null ? $post->user->resumes()->findOrFail($resumeId) : null;
        if ($ai->remaining($post->user) === 0) {
            $this->addError('letter', (new AiLimitReached)->getMessage());

            return;
        }

        $letter = $post->user->coverLetters()->create([
            'job_post_id' => $post->id,
            'resume_id' => $resume?->id,
            'language' => $language,
            'tone' => $tone,
            'length' => $length,
            // The company and its place, for the candidate to complete with the street.
            'recipient' => implode("\n", array_filter([$post->company, $post->location])),
            'status' => CoverLetter::WRITING,
        ]);
        WriteCoverLetter::dispatch($letter);

        $this->redirectRoute('letters.edit', ['id' => $letter->id]);
    }

    /** Adds the job to the person's applications, in Saved. */
    public function track(): void
    {
        $post = $this->post($this->postId);
        $application = $post->user->applications()->where('job_post_id', $post->id)->first() ?? Application::track($post->user, [
            'job_post_id' => $post->id,
            'title' => $post->title !== '' ? $post->title : __('Job post'),
            'company' => $post->company,
            'location' => $post->location,
            'url' => $post->url,
            'source' => $post->url !== null ? (string) preg_replace('/^www\./', '', (string) parse_url($post->url, PHP_URL_HOST)) : '',
        ]);
        $this->redirectRoute('applications.show', ['id' => $application->id], navigate: true);
    }

    public function delete(): void
    {
        $this->post($this->postId)->delete();
        $this->redirectRoute('jobs');
    }

    public function render(): View
    {
        $post = $this->post($this->postId);
        $user = $post->user;

        return view('livewire.jobs.show', [
            'post' => $post,
            'match' => $post->status === JobPost::READY ? JobMatch::of($post, $user->careerProfile()) : null,
            'resumes' => $user->resumes()->get(),
            'letters' => $user->coverLetters()->where('job_post_id', $post->id)->get(),
            'application' => $user->applications()->where('job_post_id', $post->id)->first(),
        ]);
    }

    /** One of the signed-in person's posts; anyone else's simply isn't found. */
    private function post(int $id): JobPost
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->jobPosts()->findOrFail($id);
    }
}
