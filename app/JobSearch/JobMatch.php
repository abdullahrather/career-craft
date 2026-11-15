<?php

declare(strict_types=1);

namespace App\JobSearch;

use App\Ai\ProfileFacts;
use App\Models\JobPost;
use App\Models\Profile;
use App\Models\ProfileLanguage;
use App\Models\Skill;

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
        $text = ProfileFacts::text($profile);

        $find = function (string $skill) use ($listed, $text): array {
            $names = Skills::names($skill);
            foreach ($listed as $name) {
                if (in_array(Skills::key($name), $names, true)) {
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

    /** @param  list<Found>  $found */
    public static function matched(array $found): int
    {
        return count(array_filter($found, fn (array $item): bool => $item['where'] !== null));
    }
}
