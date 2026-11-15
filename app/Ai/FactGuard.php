<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\FactChecker;
use App\Ai\Agents\FactVerifier;
use App\Ai\Agents\Writer;
use App\Models\User;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Checks AI-written text against the candidate's own facts, so nothing invented reaches a
 * CV unnoticed: a new employer, school, degree, title, technology, number or result.
 *
 * Numbers are compared directly. Everything else goes to the fact checker, and each claim
 * it lists to the fact verifier for a second look. A claim is dropped only when it isn't
 * really in the suggestion, when the sources contain it word for word, or when the
 * verifier backs it with words that really are in the sources. So the AI's own mistakes
 * can add a flag, but never remove a true one.
 */
final readonly class FactGuard
{
    /**
     * The kinds of claim taken from the fact checker. A small model can't tell a result told
     * in new words, or an "other" claim, from honest rewording or translation: tried live, it
     * flagged most rewordings. An invented result with a number is still caught by the number
     * check, and a new name in English by the name check.
     */
    private const array TRUSTED_KINDS = ['employer', 'school', 'degree', 'certificate', 'title', 'skill', 'number', 'date'];

    /** Words that link a claim's facts without adding one, in English and German. */
    private const array LINKING_WORDS = [
        'a', 'an', 'and', 'as', 'at', 'by', 'in', 'of', 'on', 'to', 'the', 'for', 'with', 'using', 'from', 'into', 'their', 'its', 'our', 'while', 'during', 'through', 'which', 'that',
        'am', 'im', 'zu', 'zum', 'zur', 'und', 'der', 'die', 'das', 'den', 'dem', 'des', 'mit', 'für', 'von', 'bei', 'durch', 'sowie', 'ein', 'eine', 'einer',
    ];

    public function __construct(private AiRunner $ai) {}

    /**
     * @param  string  $sources  The facts the suggestion may use: the profile and the text it rewrites.
     * @param  list<array{title: string, employer: string}>  $jobs  The profile's jobs, to catch a title moved to another employer.
     * @return list<array{quote: string, kind: string}> Each claim the sources don't support.
     */
    public function check(string $suggestion, string $sources, ?User $user, array $jobs = []): array
    {
        $claims = [];
        foreach (self::newNumbers($suggestion, $sources) as $number) {
            $claims[] = ['quote' => $number, 'kind' => 'number'];
        }
        foreach (self::newNames($suggestion, $sources) as $name) {
            $claims[] = ['quote' => $name, 'kind' => 'other'];
        }
        foreach (self::movedTitles($suggestion, $jobs) as $title) {
            $claims[] = ['quote' => $title, 'kind' => 'title'];
        }

        $response = $this->ai->prompt(new FactChecker, FactChecker::input($sources, $suggestion), $user, 'fact_check');
        $listed = $response instanceof StructuredAgentResponse ? ($response->toArray()['claims'] ?? []) : [];

        foreach (is_array($listed) ? $listed : [] as $claim) {
            $quote = is_array($claim) && is_string($claim['quote'] ?? null) ? trim($claim['quote']) : '';
            $kind = is_array($claim) && in_array($claim['kind'] ?? null, FactChecker::KINDS, true) ? $claim['kind'] : 'other';
            if (! in_array($kind, self::TRUSTED_KINDS, true) || $quote === '' || ! self::contains($suggestion, $quote) || self::covered($quote, $claims)) {
                continue;
            }
            // A number the sources have, written differently ("40 %" for "40%"), isn't new.
            if (preg_match('/\p{L}/u', $quote) !== 1 && self::newNumbers($quote, $sources) === []) {
                continue;
            }
            // A placeholder such as [X%] is for the candidate to fill in, not a claim.
            $words = trim((string) preg_replace('/\[[^\]]*\]/u', '', $quote), " \t.,;:-");
            if ($words === '' || self::contains($sources, $words)) {
                continue;
            }
            // A skill told in words the sources already use ("using PostgreSQL") isn't new. Not for
            // a title or an organisation: "Developer at Acme" can be new even when both words are known.
            if ($kind === 'skill' && self::knownWords($words, $sources)) {
                continue;
            }
            if ($this->backed($quote, $suggestion, $sources, $user)) {
                continue;
            }
            $claims[] = ['quote' => $quote, 'kind' => $kind];
        }

        return $claims;
    }

    private function backed(string $quote, string $suggestion, string $sources, ?User $user): bool
    {
        $response = $this->ai->prompt(new FactVerifier, FactVerifier::input($sources, $quote, $suggestion), $user, 'fact_check');
        $answer = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
        $fact = is_string($answer['fact'] ?? null) ? trim($answer['fact']) : '';

        return ($answer['backed'] ?? false) === true && $fact !== '' && self::contains($sources, $fact);
    }

    /**
     * Numbers in the suggestion that the sources don't have. Separators are ignored, so
     * 1,000 and 1.000 are the same number.
     *
     * @return list<string>
     */
    private static function newNumbers(string $suggestion, string $sources): array
    {
        $numbers = function (string $text): array {
            preg_match_all('/\d[\d.,]*\d|\d/u', $text, $matches);

            return $matches[0];
        };
        $known = array_map(fn (string $number): string => str_replace([',', '.'], '', $number), $numbers($sources));

        return array_values(array_unique(array_filter(
            $numbers($suggestion),
            fn (string $number): bool => ! in_array(str_replace([',', '.'], '', $number), $known, true),
        )));
    }

    /**
     * Whether a claim with these words is already listed.
     *
     * @param  list<array{quote: string, kind: string}>  $claims
     */
    private static function covered(string $quote, array $claims): bool
    {
        foreach ($claims as $claim) {
            if (mb_strtolower($claim['quote']) === mb_strtolower($quote)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every word, linking words aside, is in the sources. Words are
     * compared by their first six letters, so "development" matches "developer".
     */
    private static function knownWords(string $text, string $sources): bool
    {
        $stem = fn (string $word): string => mb_substr($word, 0, 6);
        $known = array_flip(array_map($stem, self::words($sources)));
        $words = array_filter(self::words($text), fn (string $word): bool => ! in_array($word, self::LINKING_WORDS, true));

        return $words !== [] && array_diff_key(array_flip(array_map($stem, $words)), $known) === [];
    }

    /**
     * Names in an English suggestion that the sources don't have, such as an employer, a
     * school or a technology: capitalised words other than a sentence's first. Not for
     * German, which capitalises every noun.
     *
     * @return list<string>
     */
    private static function newNames(string $suggestion, string $sources): array
    {
        // Any sign of German is enough: a short German sentence may show only one.
        if (Writer::germanSigns($suggestion) > 0) {
            return [];
        }
        $known = array_flip(self::words($sources));
        // Words after the start of the text or of a sentence don't count.
        preg_match_all('/(?<![.!?:]\s)(?<!^)(?<=\s)(\p{Lu}[\p{L}\p{N}\'’+#.-]*)/u', $suggestion, $matches);
        $names = array_filter(
            array_map(fn (string $name): string => rtrim($name, '.-'), $matches[1]),
            fn (string $name): bool => array_diff_key(array_flip(self::words($name)), $known) !== [],
        );

        return array_values(array_unique($names));
    }

    /**
     * Titles from one job used with another job's employer: "Backend developer at Acme" when
     * the candidate was a backend developer elsewhere and a working student at Acme.
     *
     * @param  list<array{title: string, employer: string}>  $jobs
     * @return list<string>
     */
    private static function movedTitles(string $suggestion, array $jobs): array
    {
        $named = array_filter($jobs, fn (array $job): bool => $job['employer'] !== '' && self::contains($suggestion, $job['employer']));
        $titles = [];
        foreach ($named as $job) {
            foreach ($jobs as $other) {
                $elsewhere = $other['title'] !== '' && ! self::contains($job['title'], $other['title'])
                    && ! self::contains($suggestion, $other['employer']);
                if ($elsewhere && self::contains($suggestion, $other['title'])) {
                    $titles[] = $other['title'];
                }
            }
        }

        return array_values(array_unique($titles));
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Whether the text has these words, as whole words: "Go" isn't found in "good", or a
     * short invented claim would pass as known.
     */
    private static function contains(string $haystack, string $needle): bool
    {
        $normal = fn (string $text): string => trim((string) preg_replace('/\s+/u', ' ', $text));
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normal($needle), '/').'(?![\p{L}\p{N}])/iu';

        return $normal($needle) !== '' && preg_match($pattern, $normal($haystack)) === 1;
    }
}
