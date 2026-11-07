<?php

declare(strict_types=1);

namespace App\Import;

use App\Livewire\Profile\Languages;
use App\Livewire\Profile\PersonalDetails;
use App\Models\Profile;
use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * What an import found, in the profile's own shape. Whatever the AI or a LinkedIn export
 * returns goes through normalise() first, so only values the profile accepts are shown
 * for confirming, and only the ones the person ticks are saved.
 *
 * Dates are "2024-03" months, and an empty end means "still there".
 *
 * @phpstan-type Entry array<string, string|list<string>>
 * @phpstan-type Skipped array{name: string, reason: string}
 * @phpstan-type Draft array{personal: array<string, string>, entries: array<string, list<Entry>>, skipped: list<Skipped>}
 */
final class ProfileDraft
{
    public const array SECTIONS = ['experiences', 'educations', 'skills', 'languages', 'projects', 'certifications', 'links'];

    /** The fields key() looks at. */
    private const array KEY_FIELDS = ['title', 'employer', 'institution', 'degree', 'url', 'name'];

    /** Why an entry was left out. */
    public const string NO_START = 'no_start';

    public const string BAD_DATES = 'dates';

    public const string NO_LEVEL = 'no_level';

    public const string INCOMPLETE = 'incomplete';

    /**
     * @param  array<mixed>  $raw
     * @return Draft
     */
    public static function normalise(array $raw): array
    {
        $found = self::map($raw['personal'] ?? null);
        $personal = [
            'headline' => self::line($found['headline'] ?? null, 120),
            'summary' => self::paragraphs($found['summary'] ?? null, 2000),
            'email' => self::line($found['email'] ?? null, 255),
            'phone' => self::line($found['phone'] ?? null, 40),
            'city' => self::line($found['city'] ?? null, 255),
            'country' => self::country($found['country'] ?? null),
        ];
        // Anything the profile form would refuse is dropped rather than shown.
        foreach (array_keys(Validator::make($personal, PersonalDetails::rules())->errors()->messages()) as $field) {
            $personal[$field] = '';
        }

        $skipped = [];
        $entries = [];
        foreach (self::SECTIONS as $section) {
            $entries[$section] = [];
            $seen = [];
            foreach (self::items($raw[$section] ?? null) as $item) {
                $entry = match ($section) {
                    'experiences' => self::experience($item, $skipped),
                    'educations' => self::education($item, $skipped),
                    'skills' => self::skill($item),
                    'languages' => self::language($item, $skipped),
                    'projects' => self::project($item),
                    'certifications' => self::certification($item),
                    default => self::link($item),
                };
                if ($entry === null) {
                    continue;
                }
                $key = self::key($section, $entry);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $entries[$section][] = $entry;
                }
            }
        }

        return ['personal' => $personal, 'entries' => $entries, 'skipped' => $skipped];
    }

    /**
     * What identifies an entry, to spot one the profile already has.
     *
     * @param  Entry  $entry
     */
    public static function key(string $section, array $entry): string
    {
        $value = fn (string $field): string => mb_strtolower(self::string($entry[$field] ?? null));

        return match ($section) {
            'experiences' => $value('title').'|'.$value('employer'),
            'educations' => $value('institution').'|'.$value('degree'),
            'links' => rtrim($value('url'), '/'),
            default => $value('name'),
        };
    }

    /** @return array<string, list<string>> The keys of the entries the profile already has. */
    public static function existing(Profile $profile): array
    {
        $keys = [];
        foreach (self::SECTIONS as $section) {
            $keys[$section] = array_values(self::relation($profile, $section)->get()
                ->map(fn (Model $entry): string => self::key($section, array_map(
                    fn (string $field): string => self::string($entry->getAttribute($field)),
                    array_combine(self::KEY_FIELDS, self::KEY_FIELDS),
                )))
                ->all());
        }

        return $keys;
    }

    /**
     * Saves what the person chose: the personal fields given, and the entries ticked in
     * $use. Entries the profile already has are skipped. Returns how many were saved.
     *
     * @param  Draft  $draft
     * @param  array<string, string>  $personal  Field => value, already validated.
     * @param  array<string, array<int, bool>>  $use  Section => entry index => chosen.
     */
    public static function apply(Profile $profile, array $draft, array $personal, array $use): int
    {
        return DB::transaction(function () use ($profile, $draft, $personal, $use): int {
            $count = 0;
            if ($personal !== []) {
                if (array_key_exists('summary', $personal)) {
                    $personal['summary'] = $personal['summary'] !== '' ? $personal['summary'] : null;
                }
                $profile->update($personal);
                $count += count($personal);
            }

            $existing = self::existing($profile);
            foreach ($draft['entries'] as $section => $items) {
                $relation = self::relation($profile, $section);
                $last = $relation->max('position');
                $position = is_numeric($last) ? (int) $last : 0;
                foreach ($items as $index => $entry) {
                    $key = self::key($section, $entry);
                    if (! ($use[$section][$index] ?? false) || in_array($key, $existing[$section], true)) {
                        continue;
                    }
                    $existing[$section][] = $key;
                    $relation->create([...self::attributes($section, $entry), 'position' => ++$position]);
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<Skipped>  $skipped
     * @return Entry|null
     */
    private static function experience(array $item, array &$skipped): ?array
    {
        $entry = [
            'title' => self::line($item['title'] ?? null, 255),
            'employer' => self::line($item['employer'] ?? null, 255),
            'city' => self::line($item['city'] ?? null, 255),
            'country' => self::country($item['country'] ?? null),
            'starts_on' => self::month($item['starts_on'] ?? null),
            'ends_on' => self::month($item['ends_on'] ?? null),
            'description' => self::paragraphs($item['description'] ?? null, 2000),
            'achievements' => self::lines($item['achievements'] ?? null),
        ];
        $name = implode(', ', array_filter([$entry['title'], $entry['employer']]));
        // A description that only repeats the bullet points would show them twice on a CV.
        $rest = str_replace($entry['achievements'], '', $entry['description']);
        if ($entry['achievements'] !== [] && preg_replace('/[\s.,;:•·*–-]+/u', '', $rest) === '') {
            $entry['description'] = '';
        }

        return self::checkDates($entry, $name, $entry['title'] !== '' && $entry['employer'] !== '', $skipped, startRequired: true);
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<Skipped>  $skipped
     * @return Entry|null
     */
    private static function education(array $item, array &$skipped): ?array
    {
        $entry = [
            'institution' => self::line($item['institution'] ?? null, 255),
            'degree' => self::line($item['degree'] ?? null, 255),
            'field' => self::line($item['field'] ?? null, 255),
            'city' => self::line($item['city'] ?? null, 255),
            'country' => self::country($item['country'] ?? null),
            'starts_on' => self::month($item['starts_on'] ?? null),
            'ends_on' => self::month($item['ends_on'] ?? null),
            'grade' => self::line($item['grade'] ?? null, 40),
            'description' => self::paragraphs($item['description'] ?? null, 2000),
        ];
        $name = implode(', ', array_filter([$entry['degree'], $entry['institution']]));

        return self::checkDates($entry, $name, $entry['institution'] !== '' && $entry['degree'] !== '', $skipped, startRequired: true);
    }

    /**
     * @param  array<mixed>  $item
     * @return Entry|null
     */
    private static function skill(array $item): ?array
    {
        $name = self::line($item['name'] ?? null, 80);

        return $name !== '' ? ['name' => $name] : null;
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<Skipped>  $skipped
     * @return Entry|null
     */
    private static function language(array $item, array &$skipped): ?array
    {
        $name = self::line($item['name'] ?? null, 60);
        $level = self::line($item['level'] ?? null, 6);
        $level = strtolower($level) === 'native' ? 'native' : strtoupper($level);
        if ($name === '') {
            return null;
        }
        if (! array_key_exists($level, Languages::levels())) {
            $skipped[] = ['name' => $name, 'reason' => self::NO_LEVEL];

            return null;
        }

        return ['name' => $name, 'level' => $level];
    }

    /**
     * @param  array<mixed>  $item
     * @return Entry|null
     */
    private static function project(array $item): ?array
    {
        $entry = [
            'name' => self::line($item['name'] ?? null, 255),
            'role' => self::line($item['role'] ?? null, 255),
            'url' => self::url($item['url'] ?? null),
            'starts_on' => self::month($item['starts_on'] ?? null),
            'ends_on' => self::month($item['ends_on'] ?? null),
            'description' => self::paragraphs($item['description'] ?? null, 2000),
            'achievements' => self::lines($item['achievements'] ?? null),
        ];
        if ($entry['ends_on'] !== '' && $entry['ends_on'] < $entry['starts_on']) {
            $entry['ends_on'] = '';
        }

        return $entry['name'] !== '' ? $entry : null;
    }

    /**
     * @param  array<mixed>  $item
     * @return Entry|null
     */
    private static function certification(array $item): ?array
    {
        $entry = [
            'name' => self::line($item['name'] ?? null, 255),
            'issuer' => self::line($item['issuer'] ?? null, 255),
            'issued_on' => self::month($item['issued_on'] ?? null),
            'expires_on' => self::month($item['expires_on'] ?? null),
            'url' => self::url($item['url'] ?? null),
        ];
        if ($entry['expires_on'] !== '' && $entry['expires_on'] < $entry['issued_on']) {
            $entry['expires_on'] = '';
        }

        return $entry['name'] !== '' ? $entry : null;
    }

    /**
     * @param  array<mixed>  $item
     * @return Entry|null
     */
    private static function link(array $item): ?array
    {
        $url = self::url($item['url'] ?? null);
        if ($url === '') {
            return null;
        }
        $label = self::line($item['label'] ?? null, 60);
        $host = parse_url($url, PHP_URL_HOST);

        return ['label' => $label !== '' ? $label : mb_substr(preg_replace('/^www\./', '', is_string($host) ? $host : '') ?? '', 0, 60), 'url' => $url];
    }

    /**
     * Keeps an entry whose dates make sense, and notes why one was left out.
     *
     * @param  Entry  $entry
     * @param  list<Skipped>  $skipped
     * @return Entry|null
     */
    private static function checkDates(array $entry, string $name, bool $complete, array &$skipped, bool $startRequired): ?array
    {
        $start = self::string($entry['starts_on']);
        $end = self::string($entry['ends_on']);
        $reason = match (true) {
            ! $complete => self::INCOMPLETE,
            $startRequired && $start === '' => self::NO_START,
            $start > now()->format('Y-m'), $end !== '' && $end < $start => self::BAD_DATES,
            default => null,
        };
        if ($reason === null) {
            return $entry;
        }
        // Something with no name at all isn't worth mentioning.
        if ($name !== '') {
            $skipped[] = ['name' => $name, 'reason' => $reason];
        }

        return null;
    }

    /**
     * The columns to save for an entry.
     *
     * @param  Entry  $entry
     * @return array<string, mixed>
     */
    private static function attributes(string $section, array $entry): array
    {
        $attributes = $entry;
        foreach (['starts_on', 'ends_on', 'issued_on', 'expires_on'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $attributes[$field] !== '' ? self::string($attributes[$field]).'-01' : null;
            }
        }
        if (array_key_exists('description', $attributes) && $attributes['description'] === '') {
            $attributes['description'] = null;
        }
        if ($section === 'skills') {
            // A CV rarely says how good someone is; the person sets it afterwards.
            $attributes['level'] = 2;
        }

        return $attributes;
    }

    /** @return HasMany<Model, Profile> */
    private static function relation(Profile $profile, string $section): HasMany
    {
        /** @var HasMany<Model, Profile> */
        return match ($section) {
            'experiences' => $profile->experiences(),
            'educations' => $profile->educations(),
            'skills' => $profile->skills(),
            'languages' => $profile->languages(),
            'projects' => $profile->projects(),
            'certifications' => $profile->certifications(),
            default => $profile->links(),
        };
    }

    /** @return array<mixed> */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<array<mixed>> */
    private static function items(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }

    /** One line of text, at most $max characters. */
    private static function line(mixed $value, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', self::string($value)) ?? ''), 0, $max);
    }

    /** Text that may have several lines. */
    private static function paragraphs(mixed $value, int $max): string
    {
        $text = preg_replace(["/[ \t]+/u", "/\n{3,}/"], [' ', "\n\n"], str_replace("\r\n", "\n", self::string($value))) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    /** @return list<string> Bullet points: a list, or one per line. */
    private static function lines(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/\R/', self::string($value));
        $lines = array_map(fn (mixed $line): string => self::line(preg_replace('/^[\s•·▪*–-]+/u', '', self::string($line)), 500), $items ?: []);

        return array_slice(array_values(array_filter($lines, fn (string $line): bool => $line !== '')), 0, 20);
    }

    /** A "2024-03" month, or "" when it isn't one. A year alone counts as January. */
    private static function month(mixed $value): string
    {
        $value = self::string($value);
        if (preg_match('/^\d{4}$/', $value) === 1) {
            $value .= '-01';
        }
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $match) !== 1) {
            return '';
        }

        return (int) $match[1] >= 1950 && (int) $match[1] <= (int) now()->format('Y') + 10 ? $value : '';
    }

    /** A two-letter country code, from a code or an English or German name. */
    private static function country(mixed $value): string
    {
        $value = self::string($value);
        if (preg_match('/^[A-Za-z]{2}$/', $value) === 1 && in_array(strtoupper($value), Countries::codes(), true)) {
            return strtoupper($value);
        }

        return $value !== '' ? Countries::code($value) : '';
    }

    /** A web address, with https:// added when it's missing, or "". */
    private static function url(mixed $value): string
    {
        $url = self::line($value, 500);
        if ($url !== '' && preg_match('#^https?://#i', $url) !== 1 && preg_match('#^[\w.-]+\.[a-z]{2,}(/\S*)?$#i', $url) === 1) {
            $url = 'https://'.$url;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower(is_string($scheme) ? $scheme : ''), ['http', 'https'], true) ? $url : '';
    }
}
