<?php

declare(strict_types=1);

namespace App\Livewire\ApplyQueue;

use App\Models\ApplyQueueItem;
use App\Models\CoverLetter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The apply queue: which CV and letter tone it prepares with, the jobs being prepared or
 * waiting for review, and the log of every job approved or skipped.
 */
final class Index extends Component
{
    public string $resumeId = '';

    public string $tone = 'formal';

    public function mount(): void
    {
        $user = $this->user();
        $this->resumeId = (string) $user->apply_resume_id;
        $this->tone = $user->apply_tone;
    }

    public function saveSettings(): void
    {
        $user = $this->user();
        $this->validate([
            'resumeId' => ['required', Rule::exists('resumes', 'id')->where('user_id', $user->id)],
            'tone' => ['required', Rule::in(CoverLetter::TONES)],
        ], attributes: ['resumeId' => __('CV to start from'), 'tone' => __('Letter tone')]);
        $user->forceFill(['apply_resume_id' => (int) $this->resumeId, 'apply_tone' => $this->tone])->save();
        session()->flash('settings-saved', __('Saved.'));
    }

    public function render(): View
    {
        $user = $this->user();
        $items = $user->applyQueueItems()->with(['jobPost', 'resume', 'coverLetter'])->get();
        $open = $items->filter(fn (ApplyQueueItem $item): bool => $item->approved_at === null && $item->skipped_at === null);

        return view('livewire.apply-queue.index', [
            'open' => $open,
            'states' => $open->mapWithKeys(fn (ApplyQueueItem $item): array => [$item->id => $item->state()])->all(),
            'history' => $items->reject(fn (ApplyQueueItem $item): bool => $item->approved_at === null && $item->skipped_at === null)->take(100),
            'resumes' => $user->resumes()->whereNull('job_post_id')->get(),
            'queuedToday' => $user->applyQueueItems()->where('created_at', '>=', today())->count(),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
