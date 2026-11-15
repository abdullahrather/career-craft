<?php

declare(strict_types=1);

namespace App\Livewire\Jobs;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\JobSearch\JobMatch;
use App\JobSearch\JobPage;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Paste or link a job post to have it read, and the posts read so far with their scores. */
final class Index extends Component
{
    public string $url = '';

    public string $text = '';

    public function analyse(AiRunner $ai): void
    {
        $this->url = trim($this->url);
        $this->text = trim($this->text);
        $this->validate([
            'url' => ['nullable', 'required_without:text', 'url:http,https', 'max:2048'],
            'text' => ['nullable', 'required_without:url', 'string', 'min:200', 'max:'.JobPage::MAX_TEXT],
        ], [
            'url.required_without' => __('Paste the job post, or its web address.'),
            'text.required_without' => __('Paste the job post, or its web address.'),
        ], ['url' => __('Web address'), 'text' => __('Job post')]);

        $user = $this->user();
        if ($ai->remaining($user) === 0) {
            $this->addError('text', (new AiLimitReached)->getMessage());

            return;
        }

        // Pasted text wins: it's what the person sees, and it needs no fetching.
        $post = $user->jobPosts()->create($this->text !== '' ? ['text' => $this->text] : ['url' => $this->url]);
        AnalyseJobPost::dispatch($post);
        $this->redirectRoute('jobs.show', ['id' => $post->id]);
    }

    public function delete(int $id): void
    {
        $this->user()->jobPosts()->findOrFail($id)->delete();
    }

    public function render(): View
    {
        $user = $this->user();
        $profile = $user->careerProfile();
        $posts = $user->jobPosts()->get();

        return view('livewire.jobs.index', [
            'posts' => $posts,
            'scores' => $posts->mapWithKeys(fn (JobPost $post): array => [
                $post->id => $post->status === JobPost::READY ? JobMatch::of($post, $profile)['score'] : null,
            ])->all(),
            'reading' => $posts->contains('status', JobPost::READING),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
