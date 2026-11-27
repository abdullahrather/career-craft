<?php

declare(strict_types=1);

namespace App\Models;

use App\Interviews\QuestionBank;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A mock interview for an application. The app keeps its course: each main question is
 * asked, the candidate answers, the interviewer asks one follow-up on that answer, and after
 * the last one it ends. The AI only words what the interviewer says.
 *
 * @property int $id
 * @property int $application_id
 * @property string $locale
 * @property list<string> $questions
 * @property list<array{role: string, text: string, kind?: string, index?: int}> $messages
 * @property Carbon|null $finished_at
 * @property-read Application $application
 */
#[Fillable(['locale', 'questions', 'messages', 'finished_at'])]
final class MockInterview extends Model
{
    /** The longest answer kept. */
    public const int ANSWER_LENGTH = 5000;

    /** Starts one with the questions picked for the application. */
    public static function start(Application $application): self
    {
        return $application->mockInterviews()->create([
            'locale' => QuestionBank::locale($application),
            'questions' => self::plan($application),
            'messages' => [],
        ]);
    }

    /**
     * The main questions: an opener, up to three about the job (the AI's from the post, or
     * else those for the kind of role), one for the level, one about how the candidate works,
     * and why this company.
     *
     * @return list<string>
     */
    public static function plan(Application $application): array
    {
        $bank = QuestionBank::for($application);
        $job = array_column($application->questions ?? [], 'question');

        return array_values(array_filter([
            $bank['behavioural'][0],
            ...array_slice($job !== [] ? $job : $bank['role'] ?? [], 0, 3),
            $bank['level'][0] ?? null,
            $bank['behavioural'][1],
            $bank['company'][0],
        ]));
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * What the interviewer does next: open with the first question, follow up on an answer,
     * ask the next question, or close. Null while it's the candidate's turn or it's over.
     *
     * @return array{step: string, index: int}|null
     */
    public function next(): ?array
    {
        $messages = $this->messages;
        if ($this->finished_at !== null) {
            return null;
        }
        if ($messages === []) {
            return ['step' => 'open', 'index' => 0];
        }
        if ($messages[count($messages) - 1]['role'] !== 'candidate') {
            return null;
        }
        $asked = collect($messages)->last(fn (array $message): bool => $message['role'] === 'interviewer');
        $index = (int) ($asked['index'] ?? 0);
        if (($asked['kind'] ?? '') === 'question') {
            return ['step' => 'follow-up', 'index' => $index];
        }

        return $index + 1 < count($this->questions) ? ['step' => 'next', 'index' => $index + 1] : ['step' => 'close', 'index' => $index];
    }

    /** Whether the candidate is to answer now. */
    public function awaitsAnswer(): bool
    {
        $messages = $this->messages;

        return $this->finished_at === null && $messages !== [] && $messages[count($messages) - 1]['role'] === 'interviewer';
    }

    /** @param  array{step: string, index: int}  $next */
    public function addQuestion(array $next, string $text): void
    {
        $kind = match ($next['step']) {
            'follow-up' => 'follow-up',
            'close' => 'close',
            default => 'question',
        };
        $this->update([
            'messages' => [...$this->messages, ['role' => 'interviewer', 'text' => $text, 'kind' => $kind, 'index' => $next['index']]],
            'finished_at' => $kind === 'close' ? now() : null,
        ]);
    }

    public function addAnswer(string $text): void
    {
        $this->update(['messages' => [...$this->messages, ['role' => 'candidate', 'text' => $text]]]);
    }

    protected function casts(): array
    {
        return ['questions' => 'array', 'messages' => 'array', 'finished_at' => 'datetime'];
    }
}
