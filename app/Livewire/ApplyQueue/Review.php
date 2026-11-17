<?php

declare(strict_types=1);

namespace App\Livewire\ApplyQueue;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Applications\ApplyQueue;
use App\Jobs\WriteAnswers;
use App\JobSearch\JobMatch;
use App\Models\ApplyQueueItem;
use App\Models\JobPost;
use App\Models\TailoredChange;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One queued job, ready to review: the tailored CV's suggestions and the letter, on one page.
 * Approving it makes it an application with copies of what is sent; skipping keeps it in the
 * log only.
 */
final class Review extends Component
{
    #[Locked]
    public int $itemId;

    public function mount(int $id): void
    {
        $this->itemId = $this->item($id)->id;
    }

    public function approve(ApplyQueue $queue): void
    {
        $result = $queue->approve($this->item($this->itemId));
        if (is_array($result)) {
            $this->addError($result['field'], $result['message']);
        }
    }

    /** Has the AI write the answers again, such as after a failure or a profile change. */
    public function rewriteAnswers(AiRunner $ai): void
    {
        $item = $this->item($this->itemId);
        abort_unless(in_array($item->state(), ['ready', 'approved'], true) && $item->answers_status !== 'writing', 404);
        if ($ai->remaining($item->user) === 0) {
            $this->addError('answers', (new AiLimitReached)->getMessage());

            return;
        }
        $item->update(['answers_status' => 'writing']);
        WriteAnswers::dispatch($item);
    }

    public function skip(): void
    {
        $item = $this->item($this->itemId);
        abort_if($item->approved_at !== null, 404);
        $item->update(['skipped_at' => now()]);
        $this->redirectRoute('apply-queue', navigate: true);
    }

    public function render(): View
    {
        $item = $this->item($this->itemId);
        $post = $item->jobPost;

        return view('livewire.apply-queue.review', [
            'item' => $item,
            'state' => $item->state(),
            'match' => $post !== null && $post->status === JobPost::READY ? JobMatch::of($post, $item->user->careerProfile()) : null,
            'profile' => $item->user->careerProfile(),
            'undecided' => $item->resume?->changes()->where('status', TailoredChange::READY)->count() ?? 0,
        ]);
    }

    private function item(int $id): ApplyQueueItem
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applyQueueItems()->with(['jobPost', 'resume', 'coverLetter', 'application'])->findOrFail($id);
    }
}
