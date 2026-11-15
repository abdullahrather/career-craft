<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Ai\Agents\Writer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\AiSettings;
use App\Ai\FactGuard;
use App\Ai\ProfileFacts;
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
 * The fact guard checks every suggestion against the profile and the text it rewrites. A
 * suggestion with a claim they don't support, or one that couldn't be checked, can only be
 * accepted once the candidate confirms it.
 *
 * @phpstan-type Change array{from: string, to: string, flags: list<array{quote: string, kind: string}>, checked: bool, confirmed: bool}
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

    /**
     * Suggested changes not yet accepted or rejected, with what the fact guard found. Locked,
     * so a flag can only be cleared by confirming it.
     *
     * @var list<Change>
     */
    #[Locked]
    public array $changes = [];

    public string $notice = '';

    public function suggest(AiRunner $ai, FactGuard $guard, string $tool, string $language = ''): void
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
        abort_unless($user instanceof User, 403);
        $profile = $user->careerProfile()->load(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        $facts = ProfileFacts::text($profile);
        $jobs = ProfileFacts::jobs($profile);
        $writer = new Writer($tool, $language === 'de' ? 'German' : 'English');
        $changes = [];
        try {
            // One request per line; a line that appears twice is asked about once.
            foreach (array_values(array_unique($lines)) as $from) {
                $response = $ai->prompt($writer, $writer->input($from), $user, 'writing_'.$tool);
                $answer = $response instanceof StructuredAgentResponse ? ($response->toArray()['text'] ?? null) : null;
                $to = is_string($answer) ? trim($answer) : '';
                if ($to !== '' && $to !== $from) {
                    $changes[] = $this->checked($guard, $from, $to, $facts, $jobs, $user);
                }
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
        if ($changes === []) {
            $this->notice = __('No changes suggested: this already reads well.');
        }
    }

    /** Marks a flagged suggestion as true, so it can be accepted. */
    public function confirm(int $index): void
    {
        $this->change($index);
        $this->changes[$index]['confirmed'] = true;
    }

    /** Puts one suggested change into the field, once anything flagged is confirmed. */
    public function accept(int $index): void
    {
        $this->resetErrorBag();
        $change = $this->change($index);
        if (self::blocked($change)) {
            $this->addError('writing', __('Check the facts marked under this suggestion, and confirm them first.'));

            return;
        }
        $applied = $this->apply($change);
        $this->forget($index);
        if (! $applied) {
            $this->changed();
        }
    }

    /** Accepts every suggestion that isn't waiting for a confirmation. */
    public function acceptAll(): void
    {
        $this->resetErrorBag();
        $applied = true;
        $waiting = [];
        foreach ($this->changes as $change) {
            if (self::blocked($change)) {
                $waiting[] = $change;
            } else {
                $applied = $this->apply($change) && $applied;
            }
        }
        $this->changes = $waiting;
        if ($waiting !== []) {
            $this->notice = __('Suggestions with facts to confirm were kept. Confirm or reject them one by one.');
        }
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

    /**
     * The suggestion with what the fact guard found. When the guard can't run, the
     * suggestion is marked as unchecked rather than passed.
     *
     * @param  list<array{title: string, employer: string}>  $jobs
     * @return Change
     */
    private function checked(FactGuard $guard, string $from, string $to, string $facts, array $jobs, User $user): array
    {
        try {
            $flags = $guard->check($to, $facts."\nText being edited: {$from}", $user, $jobs);
            $checked = true;
        } catch (AiLimitReached $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $flags = [];
            $checked = false;
        }

        return ['from' => $from, 'to' => $to, 'flags' => $flags, 'checked' => $checked, 'confirmed' => false];
    }

    /** @param  Change  $change */
    private static function blocked(array $change): bool
    {
        return ($change['flags'] !== [] || ! $change['checked']) && ! $change['confirmed'];
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
     * @param  Change  $change
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

    /** @return Change */
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
