<?php

declare(strict_types=1);

namespace App\JobSources;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * A company's public Ashby job board, with salaries where the company shows them. The board
 * doesn't name the company, so its name comes from the link.
 */
final class Ashby
{
    private const array MODES = ['Remote' => 'remote', 'Hybrid' => 'hybrid', 'OnSite' => 'onsite'];

    private const array PERIODS = ['1 YEAR' => 'year', '1 MONTH' => 'month', '1 HOUR' => 'hour'];

    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public function board(string $slug): array
    {
        $items = Sources::client()->get('https://api.ashbyhq.com/posting-api/job-board/'.rawurlencode($slug), ['includeCompensation' => 'true'])->throw()->json('jobs');

        $jobs = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || Sources::string($item, 'id') === '' || ! is_string($item['jobUrl'] ?? null) || ($item['isListed'] ?? true) === false) {
                continue;
            }
            [$min, $max, $currency, $period] = self::salary(data_get($item, 'compensation.summaryComponents'));
            $jobs[] = new FoundJob(
                source: 'ashby',
                externalId: Sources::string($item, 'id'),
                url: $item['jobUrl'],
                title: Normalise::title(Sources::string($item, 'title')),
                company: Str::headline($slug),
                location: Normalise::line(Sources::string($item, 'location')),
                workMode: self::MODES[Sources::string($item, 'workplaceType')] ?? (($item['isRemote'] ?? false) === true ? 'remote' : ''),
                description: Normalise::text(Sources::string($item, 'descriptionHtml')),
                postedAt: Sources::date($item, 'publishedAt'),
                salaryMin: $min,
                salaryMax: $max,
                salaryCurrency: $currency,
                salaryPeriod: $period,
            );
        }

        return $jobs;
    }

    /**
     * The salary among the pay parts, which also list bonuses and equity.
     *
     * @return array{?int, ?int, ?string, ?string}
     */
    private static function salary(mixed $components): array
    {
        foreach (is_array($components) ? $components : [] as $part) {
            $period = self::PERIODS[Sources::string($part, 'interval')] ?? null;
            $currency = Sources::string($part, 'currencyCode');
            $min = is_numeric(data_get($part, 'minValue')) ? (int) data_get($part, 'minValue') : null;
            $max = is_numeric(data_get($part, 'maxValue')) ? (int) data_get($part, 'maxValue') : null;
            if (Sources::string($part, 'compensationType') === 'Salary' && $period !== null && strlen($currency) === 3 && ($min !== null || $max !== null)) {
                return [$min, $max, $currency, $period];
            }
        }

        return [null, null, null, null];
    }
}
