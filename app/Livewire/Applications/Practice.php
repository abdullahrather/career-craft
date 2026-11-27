<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Interviews\QuestionBank;
use App\Jobs\WriteQuestions;
use App\Models\Application;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Interview questions to practise for one application: those the AI writes from its job
 * post, and common ones picked by the role, its level and the company.
 */
final class Practice extends Component
{
    #[Locked]
    public int $applicationId;

    public function mount(int $id): void
    {
        $this->applicationId = $this->application($id)->id;
    }

    public function writeQuestions(AiRunner $ai): void
    {
        $application = $this->application($this->applicationId);
        abort_unless($application->jobPost?->status === JobPost::READY && $application->questions_status !== 'writing', 404);
        if ($ai->remaining($application->user) === 0) {
            $this->addError('questions', (new AiLimitReached)->getMessage());

            return;
        }
        $application->update(['questions_status' => 'writing']);
        WriteQuestions::dispatch($application);
    }

    public function render(): View
    {
        $application = $this->application($this->applicationId);

        return view('livewire.applications.practice', [
            'application' => $application,
            'analysed' => $application->jobPost?->status === JobPost::READY,
            'bank' => QuestionBank::for($application),
            'locale' => QuestionBank::locale($application),
        ]);
    }

    private function application(int $id): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($id);
    }
}
