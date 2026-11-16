<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * The Bundesagentur für Arbeit's job search, the largest German job board. Its search
 * leaves out the description, so each job's details are asked for one by one.
 */
final class Arbeitsagentur
{
    /** The most jobs taken from one search, each one more request for its details. */
    public const int MAX_RESULTS = 25;

    private const string BASE = 'https://rest.arbeitsagentur.de/jobboerse/jobsuche-service';

    /**
     * @return list<FoundJob>
     *
     * @throws ConnectionException|RequestException When the search itself fails.
     */
    public function search(string $what, string $where): array
    {
        $results = self::client()->get(self::BASE.'/pc/v6/jobs', array_filter([
            'was' => $what,
            'wo' => $where,
            'size' => self::MAX_RESULTS,
            'page' => 1,
            // Jobs, not apprenticeships or self-employment.
            'angebotsart' => 1,
        ]))->throw()->json('ergebnisliste');

        $jobs = [];
        foreach (is_array($results) ? $results : [] as $item) {
            if (! is_array($item) || ! is_string($item['referenznummer'] ?? null)) {
                continue;
            }
            $jobs[] = $this->job($item, $this->description($item['referenznummer']));
        }

        return $jobs;
    }

    /** @param  array<mixed>  $item */
    private function job(array $item, string $description): FoundJob
    {
        $reference = Sources::string($item, 'referenznummer');
        $published = Sources::string($item, 'veroeffentlichungszeitraum.von');

        return new FoundJob(
            source: 'arbeitsagentur',
            externalId: $reference,
            url: 'https://www.arbeitsagentur.de/jobsuche/jobdetail/'.rawurlencode($reference),
            title: Normalise::title(Sources::string($item, 'stellenangebotsTitel') ?: Sources::string($item, 'hauptberuf')),
            company: Normalise::line(Sources::string($item, 'firma')),
            location: Normalise::line(Sources::string($item, 'stellenlokationen.0.adresse.ort')),
            // Home office "possible" is the most it says.
            workMode: ($item['homeofficemoeglich'] ?? false) === true ? 'hybrid' : '',
            description: $description,
            postedAt: $published !== '' ? CarbonImmutable::parse($published) : null,
        );
    }

    /** The job's description, or nothing when its details can't be had. */
    private function description(string $reference): string
    {
        try {
            $details = self::client()->get(self::BASE.'/pc/v4/jobdetails/'.base64_encode($reference))->throw()->json();
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }

        return Normalise::text(Sources::string($details, 'stellenangebotsBeschreibung'));
    }

    private static function client(): PendingRequest
    {
        // The public client id the Bundesagentur's own job search uses.
        return Sources::client()->withHeaders(['X-API-Key' => 'jobboerse-jobsuche']);
    }
}
