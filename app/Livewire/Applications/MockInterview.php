<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Ai\Agents\MockInterviewer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Models\Application;
use App\Models\MockInterview as Interview;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * A mock interview for one application, written: the interviewer's words appear as the AI
 * writes them, and the candidate types each answer.
 */
final class MockInterview extends Component
{
    #[Locked]
    public int $applicationId;

    public string $answer = '';

    public function mount(int $id): void
    {
        $this->applicationId = $this->application($id)->id;
    }

    /** Starts a new interview; the interviewer opens it straight away. */
    public function start(): void
    {
        $current = $this->interview();
        abort_if($current !== null && $current->finished_at === null, 404);
        Interview::start($this->application($this->applicationId));
        $this->js('$wire.reply()');
    }

    public function send(): void
    {
        $this->validate(['answer' => ['required', 'string', 'max:'.Interview::ANSWER_LENGTH]], attributes: ['answer' => __('Your answer')]);
        $interview = $this->interview();
        abort_unless($interview?->awaitsAnswer() === true, 404);
        $interview->addAnswer(trim($this->answer));
        $this->answer = '';
        $this->js('$wire.reply()');
    }

    /** The interviewer's next words, shown as they arrive. */
    public function reply(AiRunner $ai): void
    {
        $interview = $this->interview();
        $next = $interview?->next();
        if ($interview === null || $next === null) {
            return;
        }

        $agent = new MockInterviewer($interview);
        try {
            $text = $ai->stream($agent, $agent->turn($next), $this->user(), 'mock_interview', function (string $text): void {
                $this->stream($text, name: 'reply');
            });
        } catch (AiLimitReached $exception) {
            $this->addError('reply', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('reply', __('The interviewer could not answer. Try again in a moment.'));

            return;
        }
        if (trim($text) === '') {
            $this->addError('reply', __('The interviewer could not answer. Try again in a moment.'));

            return;
        }
        $interview->addQuestion($next, trim($text));
    }

    /** Ends the interview before its last question. */
    public function finish(): void
    {
        $interview = $this->interview();
        abort_unless($interview !== null && $interview->finished_at === null, 404);
        $interview->update(['finished_at' => now()]);
    }

    public function render(): View
    {
        $interview = $this->interview();
        $application = $this->application($this->applicationId);

        return view('livewire.applications.mock-interview', [
            'application' => $application,
            'interview' => $interview,
            'planned' => $interview === null ? count(Interview::plan($application)) : 0,
            // The interviewer's turn, for a reply that failed or was cut off.
            'due' => $interview?->next() !== null,
        ]);
    }

    /** The latest interview for the application. */
    private function interview(): ?Interview
    {
        return $this->application($this->applicationId)->mockInterviews()->latest('id')->first();
    }

    private function application(int $id): Application
    {
        return $this->user()->applications()->findOrFail($id);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
