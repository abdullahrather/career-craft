<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\ProfileLanguage;
use App\Models\Project;
use App\Models\Skill;
use DateTimeInterface;

/**
 * Everything the career profile states, as plain text: the facts the AI may use, and the
 * facts the fact guard checks a suggestion against.
 */
final class ProfileFacts
{
    public static function text(Profile $profile): string
    {
        $month = fn (?DateTimeInterface $date): string => $date?->format('Y-m') ?? 'now';
        $lines = array_filter([
            $profile->headline !== '' ? "Headline: {$profile->headline}" : '',
            (string) $profile->summary !== '' ? "Summary: {$profile->summary}" : '',
            $profile->city !== '' ? 'Lives in: '.trim("{$profile->city} {$profile->country}") : '',
        ]);

        foreach ($profile->experiences as $job) {
            /** @var Experience $job */
            $place = trim("{$job->city} {$job->country}");
            $lines[] = "Job: {$job->title} at {$job->employer}, ".($place !== '' ? "{$place}, " : '')."{$month($job->starts_on)} to {$month($job->ends_on)}";
            $lines = [...$lines, ...self::details($job->description, $job->achievements)];
        }
        foreach ($profile->educations as $education) {
            /** @var Education $education */
            $lines[] = trim("Education: {$education->degree} {$education->field} at {$education->institution}, {$month($education->starts_on)} to {$month($education->ends_on)}".($education->grade !== '' ? ", grade {$education->grade}" : ''));
            $lines = [...$lines, ...self::details($education->description, [])];
        }
        foreach ($profile->projects as $project) {
            /** @var Project $project */
            $lines[] = 'Project: '.$project->name.($project->role !== '' ? ", role {$project->role}" : '');
            $lines = [...$lines, ...self::details($project->description, $project->achievements)];
        }
        if ($profile->skills->isNotEmpty()) {
            $lines[] = 'Skills: '.$profile->skills->map(fn (Skill $skill): string => $skill->name)->implode(', ');
        }
        foreach ($profile->certifications as $certification) {
            /** @var Certification $certification */
            $lines[] = "Certificate: {$certification->name}".($certification->issuer !== '' ? " from {$certification->issuer}" : '');
        }
        if ($profile->languages->isNotEmpty()) {
            $lines[] = 'Languages: '.$profile->languages->map(fn (ProfileLanguage $language): string => trim("{$language->name} {$language->level}"))->implode(', ');
        }

        return implode("\n", $lines);
    }

    /** @return list<array{title: string, employer: string}> */
    public static function jobs(Profile $profile): array
    {
        return array_values($profile->experiences->map(fn (Experience $job): array => ['title' => $job->title, 'employer' => $job->employer])->all());
    }

    /**
     * @param  list<string>  $achievements
     * @return list<string>
     */
    private static function details(?string $description, array $achievements): array
    {
        $lines = (string) $description !== '' ? ["  {$description}"] : [];
        foreach ($achievements as $achievement) {
            $lines[] = "  - {$achievement}";
        }

        return $lines;
    }
}
