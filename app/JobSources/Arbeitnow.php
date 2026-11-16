<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Arbeitnow's free job feed, mostly jobs in Germany. It has no search: the newest jobs come
 * in pages of up to 300, and the feed asks to be used gently, so one page is taken at a time.
 */
final class Arbeitnow
{
    private const string URL = 'https://www.arbeitnow.com/api/job-board-api';

    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public function latest(): array
    {
        $items = Sources::client()->get(self::URL, ['page' => 1])->throw()->json('data');

        $jobs = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || ! is_string($item['slug'] ?? null) || ! is_string($item['url'] ?? null)) {
                continue;
            }
            $jobs[] = new FoundJob(
                source: 'arbeitnow',
                externalId: $item['slug'],
                url: $item['url'],
                title: Normalise::title(is_string($item['title'] ?? null) ? $item['title'] : ''),
                company: Normalise::line(is_string($item['company_name'] ?? null) ? $item['company_name'] : ''),
                location: Normalise::line(is_string($item['location'] ?? null) ? $item['location'] : ''),
                workMode: ($item['remote'] ?? false) === true ? 'remote' : '',
                description: Normalise::text(is_string($item['description'] ?? null) ? $item['description'] : ''),
                postedAt: is_int($item['created_at'] ?? null) ? CarbonImmutable::createFromTimestamp($item['created_at']) : null,
            );
        }

        return $jobs;
    }
}
