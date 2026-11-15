<?php

declare(strict_types=1);

namespace App\Support;

/** Comparisons of two texts: line by line for CV versions, word by word for AI suggestions. */
final class TextDiff
{
    /**
     * Each line of either text, marked as in both, only in the old one or only in the new one.
     *
     * @return list<array{type: 'same'|'removed'|'added', text: string}>
     */
    public static function lines(string $old, string $new): array
    {
        return self::compare(self::split($old), self::split($new));
    }

    /**
     * Each word of either text, marked the same way. Line breaks count as words, so a
     * changed line break shows too.
     *
     * @return list<array{type: 'same'|'removed'|'added', text: string}>
     */
    public static function words(string $old, string $new): array
    {
        $words = fn (string $text): array => preg_split('/[ \t]+|(?<=\n)|(?=\n)/u', trim(str_replace(["\r\n", "\r"], "\n", $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return self::compare($words($old), $words($new));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{type: 'same'|'removed'|'added', text: string}>
     */
    private static function compare(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        // The longest common subsequence of lines, built from the end.
        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j] ? $lengths[$i + 1][$j + 1] + 1 : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $diff = [];
        [$i, $j] = [0, 0];
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $diff[] = ['type' => 'same', 'text' => $a[$i++]];
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $diff[] = ['type' => 'removed', 'text' => $a[$i++]];
            } else {
                $diff[] = ['type' => 'added', 'text' => $b[$j++]];
            }
        }
        while ($i < $n) {
            $diff[] = ['type' => 'removed', 'text' => $a[$i++]];
        }
        while ($j < $m) {
            $diff[] = ['type' => 'added', 'text' => $b[$j++]];
        }

        return $diff;
    }

    /** @return list<string> */
    private static function split(string $text): array
    {
        $lines = array_map(fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? ''), preg_split('/\R/', $text) ?: []);

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }
}
