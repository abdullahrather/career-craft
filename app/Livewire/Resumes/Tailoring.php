<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\JobSearch\JobMatch;
use App\Models\Resume;
use App\Models\TailoredChange;
use App\Models\User;
use App\Resumes\ResumeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The changes the AI suggested for a CV tailored to a job, each shown as a diff to accept
 * or reject, with the match score before and after. A suggestion the fact guard flagged,
 * or couldn't check, is accepted only once the candidate confirms it. Polled while
 * suggestions are still being written.
 */
final class Tailoring extends Component
{
    #[Locked]
    public int $resumeId;

    public function mount(int $resumeId): void
    {
        $this->resumeId = $resumeId;
        $this->resume();
    }

    public function confirm(int $id): void
    {
        $this->change($id, TailoredChange::READY)->update(['confirmed' => true]);
    }

    public function accept(int $id): void
    {
        $change = $this->change($id, TailoredChange::READY);
        if ($change->blocked()) {
            $this->addError('tailoring', __('Check the facts marked under this suggestion, and confirm them first.'));

            return;
        }
        $change->update(['status' => TailoredChange::ACCEPTED]);
        $this->dispatch('resume-saved');
    }

    /** Accepts every suggestion that isn't waiting for a confirmation. */
    public function acceptAll(): void
    {
        $ready = $this->resume()->changes()->where('status', TailoredChange::READY)->get();
        $open = $ready->reject(fn (TailoredChange $change): bool => $change->blocked());
        TailoredChange::query()->whereKey($open->modelKeys())->update(['status' => TailoredChange::ACCEPTED]);
        if ($open->count() < $ready->count()) {
            $this->addError('tailoring', __('Suggestions with facts to confirm were kept. Confirm or reject them one by one.'));
        }
        $this->dispatch('resume-saved');
    }

    /** Rejects a suggestion, or takes back an accepted one. */
    public function reject(int $id): void
    {
        $change = $this->change($id);
        abort_unless(in_array($change->status, [TailoredChange::READY, TailoredChange::ACCEPTED], true), 404);
        $change->update(['status' => TailoredChange::REJECTED]);
        $this->dispatch('resume-saved');
    }

    public function render(): View
    {
        $resume = $this->resume();
        $post = $resume->jobPost;
        $changes = $resume->changes()->with('experience')->get();

        return view('livewire.resumes.tailoring', [
            'post' => $post,
            'changes' => $changes,
            'before' => $post !== null ? JobMatch::forDocument($post, ResumeDocument::for($resume, tailored: false)) : null,
            'after' => $post !== null ? JobMatch::forDocument($post, ResumeDocument::for($resume)) : null,
        ]);
    }

    /** One of the signed-in person's CVs; anyone else's simply isn't found. */
    private function resume(): Resume
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->resumes()->findOrFail($this->resumeId);
    }

    private function change(int $id, ?string $status = null): TailoredChange
    {
        $change = $this->resume()->changes()->findOrFail($id);
        abort_unless($status === null || $change->status === $status, 404);

        return $change;
    }
}
