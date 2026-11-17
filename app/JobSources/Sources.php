<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** What every job source request shares: who is asking, and how long to wait. */
final class Sources
{
    public static function client(): PendingRequest
    {
        return Http::withUserAgent('CareerCraft job importer')->acceptJson()->timeout(15);
    }

    /** A text or number in a source's answer, by its dotted path, or nothing. */
    public static function string(mixed $data, string $path): string
    {
        $value = data_get($data, $path);

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    /** A date in a source's answer, or nothing when it's missing or can't be read. */
    public static function date(mixed $data, string $path): ?CarbonImmutable
    {
        $value = self::string($data, $path);
        try {
            return $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
