<?php

declare(strict_types=1);

namespace App\Livewire\ApplyQueue;

use App\Applications\ApplyQueue;
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
