<?php

declare(strict_types=1);

namespace App\Livewire\Jobs;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\JobSearch\JobMatch;
use App\Models\JobPost;
use App\Models\User;
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
