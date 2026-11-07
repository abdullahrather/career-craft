<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/*
 * Every screen ships in English and German. English text is the translation key, and
 * lang/de.json holds the German. These checks fail when German is missing, unused, or
 * drops a :placeholder.
 */

/*
 * Text that Laravel and Fortify show on our behalf: the confirmation and password emails,
 * and two-factor messages. It isn't found by scanning our code, so it's listed here.
 */
const FRAMEWORK_TEXTS = [
    'Verify Email Address',
    'Verify your email address',
    'Please click the button below to verify your email address.',
    'If you did not create an account, no further action is required.',
    'Reset Password',
    'Reset your password',
    'You are receiving this email because we received a password reset request for your account.',
    'This password reset link will expire in :count minutes.',
    'If you did not request a password reset, no further action is required.',
    'Hello!',
    'Whoops!',
    'Regards,',
    'All rights reserved.',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
    'The provided password was incorrect.',
    'The provided two factor authentication code was invalid.',
    'The provided two factor recovery code was invalid.',
];

/** @return array<string, string> English text found in __(), trans() and @lang(), with where it was found. */
function translatableTexts(): array
{
    $texts = array_fill_keys(FRAMEWORK_TEXTS, 'Laravel or Fortify');
    $files = Finder::create()->files()->in([
        base_path('app'),
        base_path('resources/views'),
        base_path('routes'),
    ])->name(['*.php']);

    $pattern = '/(?:__|trans|@lang)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
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

/** @return list<string> Dotted keys of a language file, such as "between.string". */
function languageFileKeys(string $file): array
{
    /** @var array<string, mixed> $lines */
    $lines = require $file;
    unset($lines['attributes'], $lines['custom']);

    return array_keys(Arr::dot($lines));
}

it('has every English language file in German, with the same messages', function (): void {
    foreach (glob(lang_path('en/*.php')) ?: [] as $english) {
        $german = lang_path('de/'.basename($english));

        expect(file_exists($german))->toBeTrue('Missing lang/de/'.basename($english));
        expect(languageFileKeys($german))->toEqualCanonicalizing(languageFileKeys($english));
    }
});

it('keeps the same placeholders in German', function (): void {
    $mismatched = array_keys(array_filter(
        germanTexts(),
        fn (string $german, string $english): bool => placeholders($german) !== placeholders($english),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($mismatched)->toBe([]);
});
