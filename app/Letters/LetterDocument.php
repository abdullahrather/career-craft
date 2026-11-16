<?php

declare(strict_types=1);

namespace App\Letters;

use App\Models\CoverLetter;
use App\Models\Profile;
use App\Models\Resume;
use App\Support\Countries;
use Illuminate\Support\Facades\App;

/**
 * A cover letter as a page: the DIN 5008 layout German employers expect, which reads as a
 * plain business letter elsewhere too. It takes its font and colour from the CV it's sent
 * with, so the two match.
 */
final readonly class LetterDocument
{
    /** The words around the body, by language. */
    private const array WORDS = [
        'en' => ['greeting' => 'Dear :name,', 'greeting_anyone' => 'Dear Hiring Team,', 'closing' => 'Kind regards', 'enclosure' => 'Enclosure: CV', 'date' => 'j F Y'],
        'de' => ['greeting' => 'Guten Tag :name,', 'greeting_anyone' => 'Sehr geehrte Damen und Herren,', 'closing' => 'Mit freundlichen Grüßen', 'enclosure' => 'Anlage: Lebenslauf', 'date' => 'j. F Y'],
    ];

    public Profile $profile;

    public string $font;

    public string $accent;

    public function __construct(public CoverLetter $letter)
    {
        $this->profile = $letter->user->careerProfile();
        $look = $letter->resume?->look() ?? Resume::defaults('classic');
        $this->font = $look['font'];
        $this->accent = $look['accent'];
    }

    public function render(bool $pdf = false): string
    {
        $locale = App::getLocale();
        App::setLocale($this->letter->language);

        try {
            return view('letters.document', ['doc' => $this, 'pdf' => $pdf])->render();
        } finally {
            App::setLocale($locale);
        }
    }

    /** @return array<int, string> Font weight => file name. */
    public function fontFiles(): array
    {
        return [400 => "{$this->font}-400.woff2", 600 => "{$this->font}-600.woff2"];
    }

    public function name(): string
    {
        return $this->letter->user->name;
    }

    /** "Lena Brandt – Cover letter" or "Lena Brandt – Anschreiben". */
    public function title(): string
    {
        return $this->name().' – '.($this->letter->language === 'de' ? 'Anschreiben' : 'Cover letter');
    }

    /** @return list<string> The sender's place, email and phone, where known. */
    public function contact(): array
    {
        $place = implode(', ', array_filter([$this->profile->city, Countries::name($this->profile->country)]));

        return array_values(array_filter([$place, $this->profile->email, $this->profile->phone]));
    }

    /** @return list<string> */
    public function recipient(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->letter->recipient) ?: [])));
    }

    /** "Hamburg, 16. November 2026", or the date alone when the city isn't known. */
    public function dateLine(): string
    {
        return implode(', ', array_filter([$this->profile->city, now()->translatedFormat($this->words('date'))]));
    }

    public function greeting(): string
    {
        $contact = trim($this->letter->contact);

        return $contact === '' ? $this->words('greeting_anyone') : str_replace(':name', $contact, $this->words('greeting'));
    }

    /** @return list<string> */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', (string) $this->letter->body) ?: [])));
    }

    public function closing(): string
    {
        return $this->words('closing');
    }

    /** "Anlage: Lebenslauf" when a CV goes with the letter. */
    public function enclosure(): ?string
    {
        return $this->letter->resume_id !== null ? $this->words('enclosure') : null;
    }

    private function words(string $key): string
    {
        return self::WORDS[$this->letter->language][$key] ?? self::WORDS['en'][$key];
    }
}
