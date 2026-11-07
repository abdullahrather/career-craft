<?php

declare(strict_types=1);

namespace App\Resumes;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\ProfileLanguage;
use App\Models\ProfileLink;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Smalot\PdfParser\Parser;

/**
 * Reads an exported PDF back as plain text, the way an applicant tracking system does,
 * and lists anything from the CV that doesn't come through.
 *
 * @phpstan-type Result array{text: string, pages: int, checked: int, missing: list<string>}
 */
final class AtsCheck
{
    /** @return Result */
    public static function run(string $pdf, ResumeDocument $doc): array
    {
        $parsed = (new Parser)->parseContent($pdf);
        $text = trim($parsed->getText());
        $expected = $doc->inLanguage(fn (): array => self::expected($doc));

        $plain = self::normalise($text);
        $digits = preg_replace('/\D/', '', $text) ?? '';
        $missing = [];
        foreach ($expected as $item) {
            // Whole words only: "08/2019Working student" is how a parser would misread a title.
            $found = preg_match('/^\+?[\d\s()\/.-]+$/', $item) === 1
                ? str_contains($digits, preg_replace('/\D/', '', $item) ?? '')
                : preg_match('/(?<![\p{L}\p{N}])'.preg_quote(self::normalise($item), '/').'(?![\p{L}\p{N}])/u', $plain) === 1;
            if (! $found) {
                $missing[] = $item;
            }
        }

        return ['text' => $text, 'pages' => count($parsed->getPages()), 'checked' => count($expected), 'missing' => $missing];
    }

    /** @return list<string> What a parser should find: the name, contact details, headings and the main facts. */
    private static function expected(ResumeDocument $doc): array
    {
        $items = [$doc->name(), $doc->profile->email, $doc->profile->phone];
        foreach ($doc->sections() as $section) {
            $items[] = $section['title'];
            $entries = $section['content'];
            if (! $entries instanceof Collection) {
                continue;
            }
            foreach ($entries as $entry) {
                array_push($items, ...match (true) {
                    $entry instanceof Experience => [$entry->title, $entry->employer],
                    $entry instanceof Education => [$entry->institution, $entry->degree],
                    $entry instanceof Skill, $entry instanceof ProfileLanguage, $entry instanceof Project, $entry instanceof Certification => [$entry->name],
                    $entry instanceof ProfileLink => [$entry->url],
                    default => [],
                });
            }
        }

        return array_values(array_unique(array_filter($items, fn (string $item): bool => trim($item) !== '')));
    }

    /** Lower case with single spaces, so line breaks and capitals don't count as differences. */
    private static function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? ''));
    }
}
