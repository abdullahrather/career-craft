<?php

declare(strict_types=1);

namespace App\JobSources;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/** Company job boards on Greenhouse, Lever and Ashby, found from a link to one. */
final class CompanyBoards
{
    /** Each board and the name shown for it. */
    public const array NAMES = ['greenhouse' => 'Greenhouse', 'lever' => 'Lever', 'lever-eu' => 'Lever', 'ashby' => 'Ashby'];

    /** Where each board's pages are, by their address. */
    private const array HOSTS = [
        'boards.greenhouse.io' => 'greenhouse',
        'job-boards.greenhouse.io' => 'greenhouse',
        'jobs.lever.co' => 'lever',
        'jobs.eu.lever.co' => 'lever-eu',
        'jobs.ashbyhq.com' => 'ashby',
    ];

    /**
     * The board and company in a link to a job board or one of its jobs, such as
     * "https://jobs.lever.co/kranich/…", or nothing for another link.
     *
     * @return array{board: string, slug: string}|null
     */
    public static function parse(string $link): ?array
    {
        $link = trim($link);
        $parts = parse_url(preg_match('~^https?://~i', $link) === 1 ? $link : "https://{$link}");
        if ($parts === false) {
            return null;
        }
        $board = self::HOSTS[strtolower($parts['host'] ?? '')] ?? null;
        $slug = explode('/', trim($parts['path'] ?? '', '/'))[0];
        // Greenhouse's embedded board names the company in the query: "/embed/job_board?for=kranich".
        if ($board === 'greenhouse' && $slug === 'embed') {
            parse_str($parts['query'] ?? '', $query);
            $slug = is_string($query['for'] ?? null) ? $query['for'] : '';
        }

        return $board !== null && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $slug) === 1 ? ['board' => $board, 'slug' => $slug] : null;
    }

    /**
     * Every open job on a board.
     *
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException
     */
    public static function jobs(string $board, string $slug): array
    {
        return match ($board) {
            'greenhouse' => (new Greenhouse)->board($slug),
            'lever' => (new Lever)->board($slug),
            'lever-eu' => (new Lever)->board($slug, eu: true),
            'ashby' => (new Ashby)->board($slug),
            default => [],
        };
    }
}
