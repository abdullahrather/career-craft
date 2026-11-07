<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Every screen ships in English and German. English text is the translation key, and
 * lang/de.json holds the German. These checks fail when German is missing, unused, or
 * drops a :placeholder.
 */

/** @return array<string, string> English text found in __(), trans() and @lang(), with where it was found. */
function translatableTexts(): array
{
    $files = Finder::create()->files()->in([
        base_path('app'),
        base_path('resources/views'),
        base_path('routes'),
    ])->name(['*.php']);

    $pattern = '/(?:__|trans|@lang)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
    $texts = [];
    foreach ($files as $file) {
        preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $text = stripslashes($match[1] !== '' ? $match[1] : ($match[2] ?? ''));
            // "validation.required" style keys come from Laravel's own language files.
            if ($text === '' || preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $text)) {
                continue;
            }
            $texts[$text] = $file->getRelativePathname();
        }
    }

    return $texts;
}

/** @return array<string, string> */
function germanTexts(): array
{
    /** @var array<string, string> */
    return json_decode((string) file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return list<string> */
function placeholders(string $text): array
{
    preg_match_all('/:([a-zA-Z_]+)/', $text, $matches);
    $names = $matches[1];
    sort($names);

    return $names;
}

it('has German for every text on screen', function (): void {
    $missing = array_diff_key(translatableTexts(), germanTexts());

    expect($missing)->toBe([], 'No German for: '.implode(' | ', array_keys($missing)));
});

it('has no German that nothing uses', function (): void {
    $unused = array_keys(array_diff_key(germanTexts(), translatableTexts()));

    expect($unused)->toBe([]);
});

it('keeps the same placeholders in German', function (): void {
    $mismatched = array_keys(array_filter(
        germanTexts(),
        fn (string $german, string $english): bool => placeholders($german) !== placeholders($english),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($mismatched)->toBe([]);
});
