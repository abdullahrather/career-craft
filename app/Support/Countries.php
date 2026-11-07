<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;
use Symfony\Component\Intl\Countries as IntlCountries;

/** Country names in the visitor's language, from the Unicode CLDR data. */
final class Countries
{
    /** @return array<string, string> ISO code => name, sorted by name. */
    public static function options(): array
    {
        return IntlCountries::getNames(App::getLocale());
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_values(IntlCountries::getCountryCodes());
    }

    public static function name(string $code): string
    {
        return $code !== '' && IntlCountries::exists($code) ? IntlCountries::getName($code, App::getLocale()) : '';
    }
}
