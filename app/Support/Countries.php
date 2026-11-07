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

    /** The ISO code for a country name in English or German, such as "Germany" or "Deutschland". */
    public static function code(string $name): string
    {
        $name = mb_strtolower(trim($name));
        foreach (['en', 'de'] as $locale) {
            $code = array_search($name, array_map('mb_strtolower', IntlCountries::getNames($locale)), true);
            if (is_string($code)) {
                return $code;
            }
        }

        return '';
    }

    public static function name(string $code): string
    {
        return $code !== '' && IntlCountries::exists($code) ? IntlCountries::getName($code, App::getLocale()) : '';
    }
}
