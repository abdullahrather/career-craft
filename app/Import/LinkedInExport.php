<?php

declare(strict_types=1);

namespace App\Import;

use App\Support\Countries;
use ZipArchive;

/**
 * Reads the data export LinkedIn lets members download (Settings, Data privacy, Get a
 * copy of your data): a ZIP of CSV files. Returns the shape ProfileDraft::normalise takes.
 */
final class LinkedInExport
{
    /** The most one CSV file may unpack to, against zip bombs. */
    private const int MAX_CSV = 5 * 1024 * 1024;

    /** LinkedIn's language levels, on the European framework. */
    private const array LEVELS = [
        'native or bilingual proficiency' => 'native',
        'full professional proficiency' => 'C1',
        'professional working proficiency' => 'B2',
        'limited working proficiency' => 'B1',
        'elementary proficiency' => 'A2',
    ];

    private const array MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /**
     * @return array<string, mixed>
     *
     * @throws UnreadableFile
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UnreadableFile('Not a ZIP file.');
        }

        try {
            $files = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (is_string($name) && str_ends_with(strtolower($name), '.csv')) {
                    $files[strtolower(basename($name))] = $name;
                }
            }
            if (array_intersect(['profile.csv', 'positions.csv', 'education.csv', 'skills.csv'], array_keys($files)) === []) {
                throw new UnreadableFile('Not a LinkedIn export.');
            }
            $csv = fn (string $file, string $column): array => isset($files[$file]) ? self::csv($zip, $files[$file], $column) : [];

            $profile = $csv('profile.csv', 'First Name')[0] ?? [];
            $emails = $csv('email addresses.csv', 'Email Address');
            usort($emails, fn (array $a, array $b): int => strcasecmp($b['Primary'] ?? '', $a['Primary'] ?? ''));
            [$city, $country] = self::place($profile['Geo Location'] ?? '');

            return [
                'personal' => [
                    'headline' => $profile['Headline'] ?? '',
                    'summary' => $profile['Summary'] ?? '',
                    'email' => $emails[0]['Email Address'] ?? '',
                    'phone' => $csv('phonenumbers.csv', 'Number')[0]['Number'] ?? '',
                    'city' => $city,
                    'country' => $country,
                ],
                'experiences' => array_map(function (array $row): array {
                    [$city, $country] = self::place($row['Location'] ?? '');
                    [$description, $achievements] = self::bullets($row['Description'] ?? '');

                    return [
                        'title' => $row['Title'] ?? '',
                        'employer' => $row['Company Name'] ?? '',
                        'city' => $city,
                        'country' => $country,
                        'starts_on' => self::month($row['Started On'] ?? ''),
                        'ends_on' => self::month($row['Finished On'] ?? ''),
                        'description' => $description,
                        'achievements' => $achievements,
                    ];
                }, $csv('positions.csv', 'Company Name')),
                'educations' => array_map(fn (array $row): array => [
                    'institution' => $row['School Name'] ?? '',
                    'degree' => $row['Degree Name'] ?? '',
                    'starts_on' => self::month($row['Start Date'] ?? ''),
                    'ends_on' => self::month($row['End Date'] ?? ''),
                    'description' => $row['Notes'] ?? '',
                ], $csv('education.csv', 'School Name')),
                'skills' => array_map(fn (array $row): array => ['name' => $row['Name'] ?? ''], $csv('skills.csv', 'Name')),
                'languages' => array_map(fn (array $row): array => [
                    'name' => $row['Name'] ?? '',
                    'level' => self::LEVELS[strtolower(trim($row['Proficiency'] ?? ''))] ?? '',
                ], $csv('languages.csv', 'Name')),
                'projects' => array_map(fn (array $row): array => [
                    'name' => $row['Title'] ?? '',
                    'url' => $row['Url'] ?? '',
                    'starts_on' => self::month($row['Started On'] ?? ''),
                    'ends_on' => self::month($row['Finished On'] ?? ''),
                    'description' => $row['Description'] ?? '',
                ], $csv('projects.csv', 'Title')),
                'certifications' => array_map(fn (array $row): array => [
                    'name' => $row['Name'] ?? '',
                    'issuer' => $row['Authority'] ?? '',
                    'issued_on' => self::month($row['Started On'] ?? ''),
                    'expires_on' => self::month($row['Finished On'] ?? ''),
                    'url' => $row['Url'] ?? '',
                ], $csv('certifications.csv', 'Name')),
                // Websites look like "[PORTFOLIO:https://example.com,OTHER:https://...]".
                'links' => array_map(
                    fn (string $url): array => ['url' => $url],
                    preg_match_all('#https?://[^\s,\]]+#i', $profile['Websites'] ?? '', $match) ? $match[0] : [],
                ),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * The rows of a CSV file, keyed by column. Some files start with notes before the
     * header, so the header is the first row that has the expected column.
     *
     * @return list<array<string, string>>
     */
    private static function csv(ZipArchive $zip, string $name, string $column): array
    {
        $size = $zip->statName($name)['size'] ?? null;
        $content = is_int($size) && $size <= self::MAX_CSV ? $zip->getFromName($name) : false;
        if (! is_string($content)) {
            return [];
        }
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '');
        rewind($stream);

        $header = null;
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $row = array_map(fn (?string $cell): string => trim((string) $cell), $row);
            if ($header === null) {
                $header = in_array($column, $row, true) ? $row : null;

                continue;
            }
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($stream);

        return $rows;
    }

    /**
     * "Hamburg, Germany" as a city and a country code.
     *
     * @return array{string, string}
     */
    private static function place(string $location): array
    {
        $parts = array_map('trim', explode(',', $location));
        $country = Countries::code((string) end($parts));
        if ($country !== '') {
            array_pop($parts);
        }

        return [trim((string) ($parts[0] ?? '')), $country];
    }

    /**
     * A description with bullet lines ("• Cut costs by 20%") split into the text and
     * the achievements.
     *
     * @return array{string, list<string>}
     */
    private static function bullets(string $text): array
    {
        $description = [];
        $achievements = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^\s*[•·▪*–-]\s*(.+)$/u', $line, $match) === 1) {
                $achievements[] = $match[1];
            } else {
                $description[] = $line;
            }
        }

        return [trim(implode("\n", $description)), $achievements];
    }

    /** "Mar 2021" or "2021" as "2021-03" or "2021". */
    private static function month(string $value): string
    {
        if (preg_match('/^([A-Za-z]{3})[a-z]*\.? (\d{4})$/', trim($value), $match) === 1 && isset(self::MONTHS[strtolower($match[1])])) {
            return sprintf('%s-%02d', $match[2], self::MONTHS[strtolower($match[1])]);
        }

        return trim($value);
    }
}
