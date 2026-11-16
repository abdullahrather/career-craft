<?php

declare(strict_types=1);

namespace App\JobSources;

use Illuminate\Support\Str;

/** Turns what sources write into one form: titles, text and the fingerprint of a job. */
final class Normalise
{
    /** A title without the gender note German job ads add, such as "(m/w/d)" or "(all genders)". */
    public static function title(string $title): string
    {
        $title = (string) preg_replace('/\s*[(\[]\s*(?:[mwfdxi]\s*[\/|,]\s*){1,3}[mwfdxi]\s*[)\]]|\s*[(\[]\s*(?:all genders|alle geschlechter|gn\*?)\s*[)\]]/iu', '', $title);

        return Str::limit(self::line($title), 250, '');
    }

    /** One line of text, with spaces tidied. */
    public static function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * HTML as plain text, keeping paragraphs and list items on their own lines. Entities are
     * decoded first: some sources send the HTML itself escaped.
     */
    public static function text(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $html = (string) preg_replace('/<\s*(?:br|\/p|\/div|\/li|\/h[1-6]|\/ul|\/ol)\b[^>]*>/iu', "\n", $html);
        $html = (string) preg_replace('/<\s*li\b[^>]*>/iu', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        // A Markdown link keeps its words, and web addresses go: "index.php" isn't a skill.
        $text = (string) preg_replace(['/\[([^\]]*)\]\([^)]*\)/u', '/https?:\/\/\S+/u'], ['$1', ''], $text);
        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)), explode("\n", $text));

        return Str::limit(trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines))), 30_000, '');
    }

    /**
     * The same job on two sources gets the same fingerprint: its title, its company without
     * the legal form, and its first place, in lower case.
     */
    public static function fingerprint(string $title, string $company, string $location): string
    {
        $company = (string) preg_replace('/\b(?:gmbh|ag|se|kg|ug|e\.?\s?v|inc|ltd|llc|co|corp)\b\.?|&/iu', '', $company);
        $place = explode(',', $location)[0];
        $key = fn (string $value): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value));

        return sha1($key(self::title($title)).'|'.$key($company).'|'.$key($place));
    }
}
