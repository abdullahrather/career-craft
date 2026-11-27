<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use App\Models\MockInterview;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * Words what the interviewer says in a mock interview. Each turn tells it what to do, from
 * the course the app keeps (MockInterview::next), and the conversation so far comes along.
 */
final class MockInterviewer implements Agent, Conversational, HasProviderOptions
{
    use Promptable;

    public function __construct(private MockInterview $interview) {}

    public function instructions(): string
    {
        $application = $this->interview->application;

        return Prompts::get('mock-interviewer', [
            'title' => $application->title,
            'company' => $application->company !== '' ? $application->company : 'the company',
            'language' => $this->interview->locale === 'de' ? 'German' : 'English',
        ]);
    }

    /**
     * What was said before this turn: the opening instruction, then the interviewer's
     * messages and the candidate's answers. An answer still to be replied to goes in the
     * turn's prompt instead.
     *
     * @return list<Message>
     */
    public function messages(): iterable
    {
        $messages = $this->interview->messages;
        if ($messages === []) {
            return [];
        }
        if ($messages[count($messages) - 1]['role'] === 'candidate') {
            array_pop($messages);
        }

        return [
            new UserMessage($this->turn(['step' => 'open', 'index' => 0])),
            ...array_map(fn (array $message): Message => $message['role'] === 'interviewer'
                ? new AssistantMessage($message['text'])
                : new UserMessage(self::answer($message['text'])), $messages),
        ];
    }

    /**
     * The prompt for this turn: the answer to reply to, if any, then what to do.
     *
     * @param  array{step: string, index: int}  $next
     */
    public function turn(array $next): string
    {
        $messages = $this->interview->messages;
        $last = $messages[count($messages) - 1] ?? null;
        $answer = $next['step'] !== 'open' && $last !== null && $last['role'] === 'candidate' ? self::answer($last['text'])."\n\n" : '';
        $question = $this->interview->questions[$next['index']] ?? '';

        return $answer.match ($next['step']) {
            'open' => "Start the interview: greet the candidate and introduce yourself as the interviewer in one or two sentences, then ask this question: «{$question}»",
            'follow-up' => 'Ask one follow-up question about this answer.',
            'next' => "Say one short neutral sentence, then ask this question: «{$question}»",
            default => 'End the interview: thank the candidate in one or two sentences and say they will hear back. Ask nothing more.',
        };
    }

    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    /** An answer, marked off so it reads as data. */
    private static function answer(string $text): string
    {
        // Until nothing changes: removing one marker can join the rest of a nested one.
        do {
            $before = $text;
            $text = (string) preg_replace('/<\/?answer>/i', '', $text);
        } while ($text !== $before);

        return "<answer>{$text}</answer>";
    }
}
