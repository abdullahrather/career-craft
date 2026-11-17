<?php

declare(strict_types=1);

namespace App\JobSearch;

use App\Models\Experience;
use App\Models\JobListing;
use App\Models\Profile;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ranks jobs by how well they fit a profile, calculated rather than guessed. Each of the
 * profile's skills found in a job's title counts three points, and in its description one;
 * each word of the profile's headline and job titles in the job's title counts two. Words
 * from the titles of jobs the person marked "not interested" take points away, unless the
 * profile itself uses them.
 *
 * Reading every description takes time, so each job's points are kept per profile and
 * worked out again only when the job or the profile changes.
 */
final class ListingRanker
{
    private const int TITLE_SKILL = 3;

    private const int DESCRIPTION_SKILL = 1;

    private const int TITLE_WORD = 2;

    private const int DISMISSED_WORD = 2;

    /** A word from dismissed titles counts at most this many times. */
    private const int DISMISSED_MAX = 3;

    /** Words too common to say anything about a job. */
    private const array COMMON = ['and', 'the', 'for', 'with', 'und', 'mit', 'der', 'die', 'das', 'den', 'des', 'von', 'für', 'zum', 'zur', 'als', 'bei', 'oder', 'auf'];

    private const int KEEP_HOURS = 24;

    /** @var list<string> */
    private array $skills;

    /** @var list<string> */
    private array $titleWords;

    /** @var array<string, int> How many dismissed titles have each word the profile doesn't use. */
    private array $dismissed;

    /** @param  list<string>  $dismissedTitles */
    public function __construct(private int $userId, ?Profile $profile, array $dismissedTitles)
    {
        $this->skills = array_values(array_filter($profile?->skills->map(fn (Skill $skill): string => $skill->name)->all() ?? []));
        $this->titleWords = array_values(array_unique(self::words(implode(' ', [$profile->headline ?? '', ...($profile?->experiences->map(fn (Experience $experience): string => $experience->title)->all() ?? [])]))));

        $own = [...$this->titleWords, ...array_merge(...array_map(fn (string $skill): array => Skills::names($skill), $this->skills ?: ['']))];
        $counts = [];
        foreach ($dismissedTitles as $title) {
            foreach (array_unique(self::words($title)) as $word) {
                if (! in_array($word, $own, true)) {
                    $counts[$word] = min(self::DISMISSED_MAX, ($counts[$word] ?? 0) + 1);
                }
            }
        }
        $this->dismissed = $counts;
    }

    /** Whether the profile has anything to rank by. */
    public function canRank(): bool
    {
        return $this->skills !== [] || $this->titleWords !== [];
    }

    /**
     * The jobs' ids, best fit first, then newest.
     *
     * @param  Collection<int, JobListing>  $candidates  With id, title, posted_at and updated_at.
     * @return list<int>
     */
    public function rank(Collection $candidates): array
    {
        $points = $this->points($candidates);
        $ranked = $candidates->sort(function (JobListing $a, JobListing $b) use ($points): int {
            $byPoints = ($points[$b->id] - $this->penalty($b->title)) <=> ($points[$a->id] - $this->penalty($a->title));

            return $byPoints !== 0 ? $byPoints : [$b->posted_at?->getTimestamp(), $b->id] <=> [$a->posted_at?->getTimestamp(), $a->id];
        });

        return array_values($ranked->map(fn (JobListing $listing): int => $listing->id)->all());
    }

    /**
     * The profile's skills a job has, as the profile names them.
     *
     * @return list<string>
     */
    public function skillsIn(JobListing $listing): array
    {
        return array_values(array_filter($this->skills, fn (string $skill): bool => Skills::in($skill, $listing->title."\n".$listing->description)));
    }

    /**
     * Each job's points, from the kept ones where the job hasn't changed since.
     *
     * @param  Collection<int, JobListing>  $candidates
     * @return array<int, int>
     */
    private function points(Collection $candidates): array
    {
        $key = "listing-points:{$this->userId}:".md5(json_encode([$this->skills, $this->titleWords], JSON_THROW_ON_ERROR));
        /** @var array<int, array{int, int}> $kept Each job's points, and when the job last changed. */
        $kept = Cache::get($key, []);

        $stale = $candidates->filter(fn (JobListing $listing): bool => ($kept[$listing->id][1] ?? null) !== $listing->updated_at?->getTimestamp());
        if ($stale->isNotEmpty()) {
            foreach ($stale->pluck('id')->chunk(200) as $ids) {
                foreach (JobListing::query()->whereKey($ids->all())->get(['id', 'title', 'description', 'updated_at']) as $listing) {
                    $kept[$listing->id] = [$this->score($listing), (int) $listing->updated_at?->getTimestamp()];
                }
            }
            // Only the jobs still listed are kept.
            $kept = array_intersect_key($kept, $candidates->keyBy('id')->all());
            Cache::put($key, $kept, now()->addHours(self::KEEP_HOURS));
        }

        return array_map(fn (array $entry): int => $entry[0], $kept);
    }

    private function score(JobListing $listing): int
    {
        $points = 0;
        foreach ($this->skills as $skill) {
            if (Skills::in($skill, $listing->title)) {
                $points += self::TITLE_SKILL;
            } elseif (Skills::in($skill, $listing->description)) {
                $points += self::DESCRIPTION_SKILL;
            }
        }
        $points += self::TITLE_WORD * count(array_intersect($this->titleWords, self::words($listing->title)));

        return $points;
    }

    private function penalty(string $title): int
    {
        $penalty = 0;
        foreach (array_unique(self::words($title)) as $word) {
            $penalty += self::DISMISSED_WORD * ($this->dismissed[$word] ?? 0);
        }

        return $penalty;
    }

    /**
     * A title's words in lower case, of three letters or more, without common ones.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}+#.]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_filter(array_map(fn (string $word): string => trim($word, '.'), $words), fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, self::COMMON, true)));
    }
}
