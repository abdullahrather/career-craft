<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CV: a template and its settings, filled from the career profile.
 *
 * @phpstan-type Settings array{font: string, accent: string, pages: int, sections: list<string>, hidden: list<string>, photo: bool, signature: bool}
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $language
 * @property string $template
 * @property array<string, mixed> $settings
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Fillable(['name', 'language', 'template', 'settings'])]
final class Resume extends Model
{
    /** Template => its default font and accent colour. */
    public const array TEMPLATES = [
        'classic' => ['font' => 'source-serif-4', 'accent' => '#1f2937'],
        'modern' => ['font' => 'instrument-sans', 'accent' => '#0f766e'],
        'compact' => ['font' => 'ibm-plex-sans', 'accent' => '#1e3a8a'],
        'lebenslauf' => ['font' => 'ibm-plex-sans', 'accent' => '#881337'],
    ];

    /** Font alias (from vite.config.js) => the CSS font stack. */
    public const array FONTS = [
        'instrument-sans' => "'Instrument Sans', Arial, sans-serif",
        'ibm-plex-sans' => "'IBM Plex Sans', Arial, sans-serif",
        'source-serif-4' => "'Source Serif 4', Georgia, serif",
        'lora' => "'Lora', Georgia, serif",
    ];

    /** Accent colours dark enough to read as text on white. */
    public const array ACCENTS = ['#1f2937', '#0f766e', '#1e3a8a', '#881337', '#166534', '#6b21a8', '#9a3412'];

    /** The profile sections a CV can show, in their usual order. */
    public const array SECTIONS = ['summary', 'experiences', 'educations', 'skills', 'languages', 'projects', 'certifications', 'links'];

    public const array LANGUAGES = ['en', 'de'];

    /** @return array<string, array{name: string, description: string}> */
    public static function templateOptions(): array
    {
        return [
            'classic' => ['name' => __('Classic'), 'description' => __('Centred and traditional, for any role.')],
            'modern' => ['name' => __('Modern'), 'description' => __('Your name and headings in colour, with a clean layout.')],
            'compact' => ['name' => __('Compact'), 'description' => __('Tight spacing, to fit a long career on fewer pages.')],
            'lebenslauf' => ['name' => __('German Lebenslauf'), 'description' => __('The German tabular CV, with photo, personal details and a signature line.')],
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The settings for a new CV with this template.
     *
     * @return Settings
     */
    public static function defaults(string $template): array
    {
        $look = self::TEMPLATES[$template] ?? self::TEMPLATES['classic'];

        return [
            'font' => $look['font'],
            'accent' => $look['accent'],
            'pages' => 2,
            'sections' => self::SECTIONS,
            'hidden' => [],
            // German CVs usually have a photo and are signed; elsewhere they usually aren't.
            'photo' => $template === 'lebenslauf',
            'signature' => $template === 'lebenslauf',
        ];
    }

    /**
     * The saved settings, with anything missing or unknown replaced by the default.
     *
     * @return Settings
     */
    public function look(): array
    {
        $defaults = self::defaults($this->template);
        $saved = $this->settings;
        $sections = self::known($saved['sections'] ?? null);
        $font = $saved['font'] ?? null;
        $accent = $saved['accent'] ?? null;

        return [
            'font' => is_string($font) && array_key_exists($font, self::FONTS) ? $font : $defaults['font'],
            'accent' => is_string($accent) && in_array($accent, self::ACCENTS, true) ? $accent : $defaults['accent'],
            'pages' => ($saved['pages'] ?? null) === 1 ? 1 : 2,
            // Sections added to the app later go at the end.
            'sections' => [...$sections, ...array_values(array_diff(self::SECTIONS, $sections))],
            'hidden' => self::known($saved['hidden'] ?? null),
            'photo' => is_bool($saved['photo'] ?? null) ? $saved['photo'] : $defaults['photo'],
            'signature' => is_bool($saved['signature'] ?? null) ? $saved['signature'] : $defaults['signature'],
        ];
    }

    /** @return list<string> The section names in a saved list that exist, each once. */
    private static function known(mixed $list): array
    {
        return is_array($list) ? array_values(array_unique(array_filter($list, fn (mixed $name): bool => is_string($name) && in_array($name, self::SECTIONS, true)))) : [];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
