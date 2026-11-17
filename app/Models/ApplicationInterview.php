<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An interview for an application, at the time as entered.
 *
 * @property int $id
 * @property int $application_id
 * @property Carbon $starts_at
 * @property int $minutes
 * @property string $location
 * @property Carbon $created_at
 * @property-read Application $application
 */
#[Fillable(['starts_at', 'minutes', 'location'])]
final class ApplicationInterview extends Model
{
    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The interview as a calendar file (RFC 5545). The time is "floating": calendars show it
     * at the hour entered, which is the hour of the interview where it takes place.
     */
    public function ics(): string
    {
        $application = $this->application;
        $escape = fn (string $text): string => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
        $summary = __('Interview: :title', ['title' => $application->title]).($application->company !== '' ? " – {$application->company}" : '');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CareerCraft//Interviews//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            "UID:interview-{$this->id}@".parse_url(config()->string('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$this->created_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$this->starts_at->format('Ymd\THis'),
            'DTEND:'.$this->starts_at->copy()->addMinutes($this->minutes)->format('Ymd\THis'),
            'SUMMARY:'.$escape($summary),
            ...($this->location !== '' ? ['LOCATION:'.$escape($this->location)] : []),
            'DESCRIPTION:'.$escape(route('applications.show', ['id' => $application->id])),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** Folds a line longer than 75 bytes, as the format asks, without splitting a character. */
    private static function fold(string $line): string
    {
        $parts = [];
        $part = '';
        foreach (mb_str_split($line) as $character) {
            // A continuation line starts with a space, which counts too.
            if (strlen($part.$character) > ($parts === [] ? 75 : 74)) {
                $parts[] = $part;
                $part = '';
            }
            $part .= $character;
        }

        return implode("\r\n ", [...$parts, $part]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'minutes' => 'integer'];
    }
}
