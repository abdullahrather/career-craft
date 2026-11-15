<?php

declare(strict_types=1);

namespace App\JobSearch;

/**
 * Skill names as they are compared: in lower case, with the names one skill commonly goes
 * by treated as the same, so "Postgres" in a post matches "PostgreSQL" in a profile.
 */
final class Skills
{
    /** Names of the same skill. The first is the one shown. */
    private const array SAME = [
        ['javascript', 'js', 'ecmascript'],
        ['typescript', 'ts'],
        ['postgresql', 'postgres'],
        ['node.js', 'nodejs', 'node'],
        ['react', 'react.js', 'reactjs'],
        ['vue.js', 'vue', 'vuejs'],
        ['kubernetes', 'k8s'],
        ['go', 'golang'],
        ['ci/cd', 'ci-cd', 'cicd'],
        ['aws', 'amazon web services'],
        ['google cloud', 'gcp', 'google cloud platform'],
        ['azure', 'microsoft azure'],
        ['c#', 'csharp'],
        ['.net', 'dotnet'],
        ['english', 'englisch'],
        ['german', 'deutsch'],
        ['french', 'französisch'],
        ['spanish', 'spanisch'],
    ];

    /** The skill in lower case, with extra spaces removed. */
    public static function key(string $skill): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $skill)));
    }

    /**
     * Every name the skill goes by, in lower case: its other names, and the name written with
     * spaces for hyphens and in the singular, so "REST-APIs" matches "REST API".
     *
     * @return list<string>
     */
    public static function names(string $skill): array
    {
        $key = self::key($skill);
        foreach (self::SAME as $names) {
            if (in_array($key, $names, true)) {
                return $names;
            }
        }
        $spaced = str_replace('-', ' ', $key);
        $singular = (string) preg_replace('/(?<=[\p{L}]{3})(?<!s)s$/u', '', $spaced);

        return array_values(array_unique([$key, $spaced, $singular]));
    }

    /**
     * Whether the text has the skill under any of its names, as whole words, or followed by
     * "kenntnisse" as German writes it: "Deutschkenntnisse", "PHP-Kenntnisse".
     */
    public static function in(string $skill, string $text): bool
    {
        foreach (self::names($skill) as $name) {
            if ($name !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?:kenntnisse)?(?![\p{L}\p{N}])/iu', $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
