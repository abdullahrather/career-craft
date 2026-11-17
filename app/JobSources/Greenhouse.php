<?php

declare(strict_types=1);

namespace App\JobSources;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/** A company's public Greenhouse job board: every open job, with its description. */
final class Greenhouse
{
    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public function board(string $slug): array
    {
        $items = Sources::client()->get('https://boards-api.greenhouse.io/v1/boards/'.rawurlencode($slug).'/jobs', ['content' => 'true'])->throw()->json('jobs');

        $jobs = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || Sources::string($item, 'id') === '' || ! is_string($item['absolute_url'] ?? null)) {
                continue;
            }
            $location = Normalise::line(Sources::string($item, 'location.name'));
            $jobs[] = new FoundJob(
                source: 'greenhouse',
                externalId: Sources::string($item, 'id'),
                url: $item['absolute_url'],
                title: Normalise::title(Sources::string($item, 'title')),
                company: Normalise::line(Sources::string($item, 'company_name')) ?: Str::headline($slug),
                location: $location,
                // Greenhouse has no field for it: the place says "Remote" when a job is.
                workMode: preg_match('/\bremote\b/iu', $location) === 1 ? 'remote' : '',
                description: Normalise::text(Sources::string($item, 'content')),
                postedAt: Sources::date($item, 'first_published') ?? Sources::date($item, 'updated_at'),
            );
        }

        return $jobs;
    }
}
