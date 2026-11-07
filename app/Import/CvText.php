<?php

declare(strict_types=1);

namespace App\Import;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/** The plain text of a CV file, for the AI to read. */
final class CvText
{
    /** About ten pages of text; a CV is never longer. */
    public const int MAX_LENGTH = 30000;

    /** The most a Word file's text part may unpack to, against zip bombs. */
    private const int MAX_DOCUMENT_XML = 20 * 1024 * 1024;

    /** @throws UnreadableFile */
    public static function from(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => self::pdf($path),
            'docx' => self::docx($path),
            default => throw new UnreadableFile('Not a PDF or Word file.'),
        };
        $text = preg_replace(["/\r\n?/", "/[ \t\x{00A0}]+/u", "/ *\n */", "/\n{3,}/"], ["\n", ' ', "\n", "\n\n"], $text) ?? '';

        return mb_substr(trim($text), 0, self::MAX_LENGTH);
    }

    private static function pdf(string $path): string
    {
        $start = @file_get_contents($path, length: 1024);
        if (! is_string($start) || ! str_contains($start, '%PDF-')) {
            throw new UnreadableFile('Not a PDF.');
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);

            return (new Parser([], $config))->parseFile($path)->getText();
        } catch (Throwable $exception) {
            throw new UnreadableFile('The PDF could not be read.', previous: $exception);
        }
    }

    private static function docx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UnreadableFile('Not a Word file.');
        }
        $size = $zip->statName('word/document.xml')['size'] ?? null;
        $xml = is_int($size) && $size <= self::MAX_DOCUMENT_XML ? $zip->getFromName('word/document.xml') : false;
        $zip->close();
        if (! is_string($xml)) {
            throw new UnreadableFile('Not a Word file.');
        }

        // Paragraphs and line breaks become new lines, tabs become spaces, the rest is markup.
        $xml = preg_replace(['/<w:tab\/>/', '/<w:br\b[^>]*\/>|<\/w:p>/'], [' ', "\n"], $xml) ?? '';

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
