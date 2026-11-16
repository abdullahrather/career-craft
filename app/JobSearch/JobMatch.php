<?php

declare(strict_types=1);

namespace App\JobSearch;

use App\Ai\ProfileFacts;
use App\Models\JobPost;
use App\Models\Profile;
use App\Models\ProfileLanguage;
use App\Models\Skill;
use App\Resumes\ResumeDocument;

/**
 * How well a profile matches a job post, calculated rather than guessed: each skill the
 * post asks for is looked up in the profile's skills and languages, then in the rest of
 * the profile, such as job titles, achievements and projects. Required skills count three
 * times as much as nice-to-have ones.
 *
 * @phpstan-type Found array{skill: string, where: 'skills'|'profile'|null}
 */
final class JobMatch
{
    private const int REQUIRED_WEIGHT = 3;

    /**
     * @return array{score: int|null, required: list<Found>, nice: list<Found>}
     */
    public static function of(JobPost $post, Profile $profile): array
    {
        $profile->loadMissing(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        $listed = [
            ...$profile->skills->map(fn (Skill $skill): string => $skill->name)->all(),
            ...$profile->languages->map(fn (ProfileLanguage $language): string => $language->name)->all(),
        ];

        return self::score($post, $listed, ProfileFacts::text($profile));
    }

    /**
     * How well what one CV shows matches the post: only its visible sections count, so a
     * tailored CV can be compared with the one it was made from.
     *
     * @return array{score: int|null, required: list<Found>, nice: list<Found>}
     */
    public static function forDocument(JobPost $post, ResumeDocument $doc): array
    {
        return self::score($post, $doc->listed(), $doc->text());
    }

    /**
     * @param  list<string>  $listed  The skills and languages listed as such.
     * @param  string  $text  Everything else, searched for the skills not listed.
     * @return array{score: int|null, required: list<Found>, nice: list<Found>}
     */
    private static function score(JobPost $post, array $listed, string $text): array
    {
        $find = function (string $skill) use ($listed, $text): array {
            $names = Skills::names($skill);
            foreach ($listed as $name) {
                // Both sides' forms, so "REST API" in a post matches "REST APIs" in a profile.
                if (array_intersect(Skills::names($name), $names) !== []) {
                    return ['skill' => $skill, 'where' => 'skills'];
                }
            }

            return ['skill' => $skill, 'where' => Skills::in($skill, $text) ? 'profile' : null];
        };
        $required = array_map($find, $post->required_skills);
        $nice = array_map($find, $post->nice_skills);

        $total = self::REQUIRED_WEIGHT * count($required) + count($nice);
        $score = $total === 0 ? null : (int) round(100 * (self::REQUIRED_WEIGHT * self::matched($required) + self::matched($nice)) / $total);

        return ['score' => $score, 'required' => $required, 'nice' => $nice];
    }

    /**
     * The post's skills the profile has, under the post's names: what the AI may bring
     * forward, and names the fact guard counts as known ("Vue.js" for a profile's "Vue").
     *
     * @return list<string>
     */
    public static function found(?JobPost $post, Profile $profile): array
    {
        if ($post === null) {
            return [];
        }
        $match = self::of($post, $profile);

        return array_column(array_filter([...$match['required'], ...$match['nice']], fn (array $item): bool => $item['where'] !== null), 'skill');
    }

    /** @param  list<Found>  $found */
    public static function matched(array $found): int
    {
        return count(array_filter($found, fn (array $item): bool => $item['where'] !== null));
    }
}
