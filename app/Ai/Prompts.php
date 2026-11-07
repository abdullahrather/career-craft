<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/**
 * The instructions the app gives the AI, kept as text files in resources/prompts so
 * they can be read, reviewed and tested like code. Placeholders look like :name.
 */
final class Prompts
{
    /** @param  array<string, string>  $values */
    public static function get(string $name, array $values = []): string
    {
        $path = resource_path("prompts/{$name}.md");
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1 || ! is_file($path)) {
            throw new RuntimeException("There is no prompt called {$name}.");
        }

        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements[':'.$key] = $value;
        }

        return trim(strtr((string) file_get_contents($path), $replacements));
    }
}
