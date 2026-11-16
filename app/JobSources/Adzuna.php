<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Adzuna's job search for Germany. It needs a free key of the runner's own (ADZUNA_APP_ID
 * and ADZUNA_APP_KEY); without one it's left out. Its descriptions are short snippets.
 */
final class Adzuna
{
    private const int RESULTS = 50;

    public static function enabled(): bool
    {
        return config('services.adzuna.app_id') && config('services.adzuna.app_key');
    }

    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public function search(string $what, string $where): array
    {
        $results = Sources::client()->get('https://api.adzuna.com/v1/api/jobs/de/search/1', array_filter([
            'app_id' => config('services.adzuna.app_id'),
            'app_key' => config('services.adzuna.app_key'),
            'what' => $what,
            'where' => $where,
            'results_per_page' => self::RESULTS,
            'content-type' => 'application/json',
        ]))->throw()->json('results');

        $jobs = [];
        foreach (is_array($results) ? $results : [] as $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_string($item['redirect_url'] ?? null)) {
                continue;
            }
            // A salary Adzuna guessed itself isn't the employer's.
            $salary = ! in_array($item['salary_is_predicted'] ?? '0', ['1', 1, true], true);
            $min = $salary && is_numeric($item['salary_min'] ?? null) ? (int) $item['salary_min'] : null;
            $max = $salary && is_numeric($item['salary_max'] ?? null) ? (int) $item['salary_max'] : null;
            $jobs[] = new FoundJob(
                source: 'adzuna',
                externalId: Sources::string($item, 'id'),
                url: $item['redirect_url'],
                title: Normalise::title(Sources::string($item, 'title')),
                company: Normalise::line(Sources::string($item, 'company.display_name')),
                location: Normalise::line(Sources::string($item, 'location.display_name')),
                workMode: '',
                description: Normalise::text(Sources::string($item, 'description')),
                postedAt: Sources::string($item, 'created') !== '' ? CarbonImmutable::parse(Sources::string($item, 'created')) : null,
                salaryMin: $min,
                salaryMax: $max,
                salaryCurrency: $min !== null || $max !== null ? 'EUR' : null,
                salaryPeriod: $min !== null || $max !== null ? 'year' : null,
            );
        }

        return $jobs;
    }
}
