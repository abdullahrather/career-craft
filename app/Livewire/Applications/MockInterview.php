<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Ai\Agents\MockInterviewer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\Whisper;
use App\Jobs\ReviewInterview;
use App\Models\Application;
use App\Models\MockInterview as Interview;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * A mock interview for one application, written: the interviewer's words appear as the AI
 * writes them, and the candidate types or speaks each answer.
 */
final class MockInterview extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $applicationId;

    public string $answer = '';

    /** @var TemporaryUploadedFile|null A spoken answer, for the Whisper server. */
    public $recording = null;

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

    /** Adds a spoken answer, turned into text by the Whisper server, to the answer box. */
    public function transcribe(Whisper $whisper): void
    {
        $interview = $this->interview();
        abort_unless($interview?->awaitsAnswer() === true && Whisper::enabled(), 404);
        $this->validate(['recording' => ['required', 'file', 'max:12288', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,video/mp4,audio/mpeg,audio/wav,audio/x-wav']], attributes: ['recording' => __('Recording')]);
        $recording = $this->recording;
        abort_unless($recording instanceof TemporaryUploadedFile, 422);
        try {
            $text = $whisper->transcribe($recording, $interview->locale);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('answer', __('Your recording could not be turned into text. Try again, or type your answer.'));

            return;
        } finally {
            $recording->delete();
            $this->recording = null;
        }
        if ($text === '') {
            $this->addError('answer', __('No words were heard in the recording.'));

            return;
        }
        $this->answer = trim($this->answer.' '.$text);
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
        if ($interview->finished_at !== null) {
            $this->review($interview);
        }
    }

    /** Ends the interview before its last question. */
    public function finish(): void
    {
        $interview = $this->interview();
        abort_unless($interview !== null && $interview->finished_at === null, 404);
        $interview->update(['finished_at' => now()]);
        $this->review($interview);
    }

    /** Asks for the feedback when it couldn't start or failed; it goes on from the answer that failed. */
    public function writeFeedback(): void
    {
        $interview = $this->interview();
        abort_unless($interview !== null && $interview->finished_at !== null && $interview->feedback_status !== 'writing' && $interview->feedback_status !== 'ready', 404);
        $this->review($interview);
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
            'answers' => collect($interview?->answers() ?? [])->keyBy('index')->all(),
            'whisper' => Whisper::enabled(),
        ]);
    }

    /** Has the AI give feedback on each answer, once the interview is over. */
    private function review(Interview $interview): void
    {
        if ($interview->answers() === []) {
            return;
        }
        if (app(AiRunner::class)->remaining($this->user()) === 0) {
            $this->addError('feedback', (new AiLimitReached)->getMessage());

            return;
        }
        $interview->update(['feedback_status' => 'writing']);
        ReviewInterview::dispatch($interview);
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
