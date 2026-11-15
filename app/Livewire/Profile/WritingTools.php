<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Ai\Agents\Writer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\AiSettings;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Throwable;

/**
 * The writing assistant under a text field of the career profile. It asks the AI for a
 * better wording and shows each change as a diff, which the candidate accepts or rejects.
 * An accepted change goes into the field only; the form's own Save button stores it.
 *
 * Bullet points are suggested and accepted one by one; a summary or description as a whole.
 * A suggestion that adds a number the text doesn't have is left out, as an invented result.
 */
final class WritingTools extends Component
{
    /** The longest text sent, the same as the longest achievements field. */
    private const int MAX_LENGTH = 4000;

    /** The most bullet points sent at once, each one a request. */
    private const int MAX_LINES = 20;

    /** The field's text, shared with the form through wire:model. */
    #[Modelable]
    public string $text = '';

    /** "bullets" for one achievement per line, or "text" for a summary or description. */
    #[Locked]
    public string $kind = 'text';

    /** @var list<string> The tools offered for this field: improve, rewrite, tone or translate. */
    #[Locked]
    public array $tools = [];

    /** @var list<array{from: string, to: string}> Suggested changes not yet accepted or rejected. */
    public array $changes = [];

    public string $notice = '';

    public function suggest(AiRunner $ai, string $tool, string $language = ''): void
    {
        abort_unless(in_array($tool, $this->tools, true), 404);
        abort_unless($tool !== 'translate' || in_array($language, ['en', 'de'], true), 404);
        $this->resetErrorBag();
        $this->changes = [];
        $this->notice = '';

        $lines = $this->lines();
        if ($lines === []) {
            $this->addError('writing', __('Write something first, then ask for a suggestion.'));

            return;
        }
        if (mb_strlen($this->text) > self::MAX_LENGTH || count($lines) > self::MAX_LINES) {
            $this->addError('writing', __('This text is too long for the writing assistant.'));

            return;
        }

        $user = Auth::user();
        $writer = new Writer($tool, $language === 'de' ? 'German' : 'English');
        $changes = [];
        $leftOut = 0;
        try {
            // One request per line; a line that appears twice is asked about once.
            foreach (array_values(array_unique($lines)) as $from) {
                $response = $ai->prompt($writer, $writer->input($from), $user instanceof User ? $user : null, 'writing_'.$tool);
                $answer = $response instanceof StructuredAgentResponse ? ($response->toArray()['text'] ?? null) : null;
                $to = is_string($answer) ? trim($answer) : '';
                if ($to === '' || $to === $from) {
                    continue;
                }
                if (self::addsNumber($from, $to)) {
                    $leftOut++;

                    continue;
                }
                $changes[] = ['from' => $from, 'to' => $to];
            }
        } catch (AiLimitReached $exception) {
            $this->addError('writing', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('writing', __('The writing assistant is not available right now. Please try again later.'));

            return;
        }

        $this->changes = $changes;
        if ($leftOut > 0) {
            $this->notice = trans_choice('{1} One suggestion was left out because it added a number your text doesn\'t have.|[2,*] :count suggestions were left out because they added numbers your text doesn\'t have.', $leftOut);
        } elseif ($changes === []) {
            $this->notice = __('No changes suggested: this already reads well.');
        }
    }

    /**
     * Whether the suggestion has a number the original doesn't, such as an invented result.
     * Separators are ignored, so 1,000 and 1.000 are the same number.
     */
    private static function addsNumber(string $from, string $to): bool
    {
        $numbers = function (string $text): array {
            preg_match_all('/\d[\d.,]*/u', $text, $matches);

            return array_map(fn (string $number): string => str_replace([',', '.'], '', $number), $matches[0]);
        };

        return array_diff($numbers($to), $numbers($from)) !== [];
    }

    /** Puts one suggested change into the field. */
    public function accept(int $index): void
    {
        $this->resetErrorBag();
        $applied = $this->apply($this->change($index));
        $this->forget($index);
        if (! $applied) {
            $this->changed();
        }
    }

    public function acceptAll(): void
    {
        $this->resetErrorBag();
        $applied = true;
        foreach ($this->changes as $change) {
            $applied = $this->apply($change) && $applied;
        }
        $this->changes = [];
        if (! $applied) {
            $this->changed();
        }
    }

    public function reject(int $index): void
    {
        $this->change($index);
        $this->forget($index);
    }

    public function dismiss(): void
    {
        $this->changes = [];
        $this->notice = '';
    }

    public function render(): View
    {
        $settings = AiSettings::current();

        return view('livewire.profile.writing-tools', ['available' => $settings->ready($settings->provider)]);
    }

    /** @return list<string> One bullet point per non-empty line, or the whole text. */
    private function lines(): array
    {
        if ($this->kind !== 'bullets') {
            $text = trim($this->text);

            return $text === '' ? [] : [$text];
        }

        $lines = array_map('trim', preg_split('/\R/', $this->text) ?: []);

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /**
     * Swaps the change's text into the field, if the text it changes is still there.
     *
     * @param  array{from: string, to: string}  $change
     */
    private function apply(array $change): bool
    {
        if ($this->kind !== 'bullets') {
            if (trim($this->text) !== $change['from']) {
                return false;
            }
            $this->text = $change['to'];

            return true;
        }

        $lines = $this->lines();
        if (! in_array($change['from'], $lines, true)) {
            return false;
        }
        // Every copy: a line that appears twice was asked about once.
        $lines = array_map(fn (string $line): string => $line === $change['from'] ? $change['to'] : $line, $lines);
        $this->text = implode("\n", $lines);

        return true;
    }

    private function changed(): void
    {
        $this->addError('writing', __('Some text has changed since the suggestion, so it was left as it is.'));
    }

    /** @return array{from: string, to: string} */
    private function change(int $index): array
    {
        return $this->changes[$index] ?? abort(404);
    }

    private function forget(int $index): void
    {
        $changes = $this->changes;
        unset($changes[$index]);
        $this->changes = array_values($changes);
    }
}
