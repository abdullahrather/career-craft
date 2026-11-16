<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Ai\Agents\LinkedInWriter;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\AiSettings;
use App\Ai\FactGuard;
use App\Ai\ProfileFacts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Suggests a LinkedIn headline and About section from the profile, to copy into LinkedIn.
 * The fact guard checks both; a suggestion with a flag, or one it couldn't check, can be
 * copied only once the candidate confirms it.
 *
 * @phpstan-type Suggestion array{text: string, flags: list<array{quote: string, kind: string}>, checked: bool, confirmed: bool}
 */
final class LinkedIn extends Component
{
    /** LinkedIn's own limits. */
    private const array LIMITS = ['headline' => 220, 'about' => 2600];

    public string $language = 'en';

    /**
     * Locked, so a flag can only be cleared by confirming it.
     *
     * @var array<string, Suggestion>
     */
    #[Locked]
    public array $suggestions = [];

    public function suggest(AiRunner $ai, FactGuard $guard): void
    {
        abort_unless(in_array($this->language, ['en', 'de'], true), 404);
        $this->resetErrorBag();
        $this->suggestions = [];
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $profile = $user->careerProfile()->load(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        $facts = ProfileFacts::text($profile);
        if (trim($facts) === '') {
            $this->addError('linkedin', __('Fill in your profile first: the suggestions come from it.'));

            return;
        }

        $writer = new LinkedInWriter($this->language === 'de' ? 'German' : 'English');
        try {
            $response = $ai->prompt($writer, $writer->input($facts), $user, 'linkedin');
            $answer = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
            $suggestions = [];
            foreach (self::LIMITS as $field => $limit) {
                $text = is_string($answer[$field] ?? null) ? Str::limit(trim($answer[$field]), $limit, '') : '';
                if ($text !== '') {
                    $suggestions[$field] = $this->checked($guard, $text, $facts, ProfileFacts::jobs($profile), $user);
                }
            }
        } catch (AiLimitReached $exception) {
            $this->addError('linkedin', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('linkedin', __('The writing assistant is not available right now. Please try again later.'));

            return;
        }

        $this->suggestions = $suggestions;
    }

    /** Marks a flagged suggestion as true, so it can be copied. */
    public function confirm(string $field): void
    {
        abort_unless(isset($this->suggestions[$field]), 404);
        $this->suggestions[$field]['confirmed'] = true;
    }

    public function render(): View
    {
        $settings = AiSettings::current();

        return view('livewire.profile.linked-in', ['available' => $settings->ready($settings->provider)]);
    }

    /**
     * The suggestion with what the fact guard found, paragraph by paragraph. When the guard
     * can't run, the suggestion is marked as unchecked rather than passed.
     *
     * @param  list<array{title: string, employer: string}>  $jobs
     * @return Suggestion
     */
    private function checked(FactGuard $guard, string $text, string $facts, array $jobs, User $user): array
    {
        $flags = [];
        $checked = true;
        foreach (array_filter(array_map('trim', preg_split('/\R\s*\R/', $text) ?: [])) as $paragraph) {
            try {
                $flags = [...$flags, ...$guard->check($paragraph, $facts, $user, $jobs)];
            } catch (AiLimitReached $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $checked = false;
            }
        }

        return ['text' => $text, 'flags' => array_values(array_unique($flags, SORT_REGULAR)), 'checked' => $checked, 'confirmed' => false];
    }
}
