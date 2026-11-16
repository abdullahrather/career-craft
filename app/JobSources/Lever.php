<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * A company's public Lever job board. Lever keeps European companies' boards apart, on
 * their own address. The board doesn't name the company, so its name comes from the link.
 */
final class Lever
{
    private const array MODES = ['remote' => 'remote', 'hybrid' => 'hybrid', 'on-site' => 'onsite'];

    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public function board(string $slug, bool $eu = false): array
    {
        $host = $eu ? 'https://api.eu.lever.co' : 'https://api.lever.co';
        $items = Sources::client()->get("{$host}/v0/postings/".rawurlencode($slug), ['mode' => 'json'])->throw()->json();

        $jobs = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || Sources::string($item, 'id') === '' || ! is_string($item['hostedUrl'] ?? null)) {
                continue;
            }
            // The description, then each list, such as "Your tasks", then the closing words.
            $html = Sources::string($item, 'description');
            foreach (is_array($item['lists'] ?? null) ? $item['lists'] : [] as $list) {
                $html .= '<h3>'.Sources::string($list, 'text').'</h3><ul>'.Sources::string($list, 'content').'</ul>';
            }
            $html .= Sources::string($item, 'additional');

            [$min, $max, $currency, $period] = self::salary($item['salaryRange'] ?? null);
            $jobs[] = new FoundJob(
                source: 'lever',
                externalId: Sources::string($item, 'id'),
                url: $item['hostedUrl'],
                title: Normalise::title(Sources::string($item, 'text')),
                company: Str::headline($slug),
                location: Normalise::line(Sources::string($item, 'categories.location')),
                workMode: self::MODES[Sources::string($item, 'workplaceType')] ?? '',
                description: Normalise::text($html),
                postedAt: is_int($item['createdAt'] ?? null) ? CarbonImmutable::createFromTimestampMs($item['createdAt']) : null,
                salaryMin: $min,
                salaryMax: $max,
                salaryCurrency: $currency,
                salaryPeriod: $period,
            );
        }

        return $jobs;
    }

    /**
     * The salary range, when it is by the year, month or hour, such as "per-year-salary".
     *
     * @return array{?int, ?int, ?string, ?string}
     */
    private static function salary(mixed $range): array
    {
        $period = preg_match('/\b(year|month|hour)\b/', Sources::string($range, 'interval'), $match) === 1 ? $match[1] : null;
        $currency = Sources::string($range, 'currency');
        $min = is_numeric(data_get($range, 'min')) ? (int) data_get($range, 'min') : null;
        $max = is_numeric(data_get($range, 'max')) ? (int) data_get($range, 'max') : null;
        if ($period === null || strlen($currency) !== 3 || ($min === null && $max === null)) {
            return [null, null, null, null];
        }

        return [$min, $max, strtoupper($currency), $period];
    }
}
