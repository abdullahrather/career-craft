<?php

declare(strict_types=1);

namespace App\Resumes;

use App\Models\Profile;
use App\Models\Resume;
use App\Models\User;
use App\Support\Countries;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * What one CV shows: the profile's content, in the order and language the CV is set to.
 *
 * @phpstan-import-type Settings from Resume
 */
final class ResumeDocument
{
    /** @var Settings */
    public readonly array $look;

    public function __construct(public readonly Resume $resume, public readonly Profile $profile, public readonly User $user)
    {
        $this->look = $resume->look();
    }

    public static function for(Resume $resume): self
    {
        return new self($resume, $resume->user->careerProfile(), $resume->user);
    }

    /**
     * The whole CV as an HTML page, in the CV's language. The preview adds page markers;
     * the PDF version loads its fonts from files sent along with it (see fontFiles()).
     */
    public function render(bool $preview = false, bool $pdf = false): string
    {
        return $this->inLanguage(fn (): string => view('resumes.document', ['doc' => $this, 'preview' => $preview, 'pdf' => $pdf])->render());
    }

    /**
     * Runs something in the CV's language, then switches back.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function inLanguage(callable $callback): mixed
    {
        $locale = App::getLocale();
        App::setLocale($this->resume->language);

        try {
            return $callback();
        } finally {
            App::setLocale($locale);
        }
    }

    /** @return array<int, string> Font weight => file name, for the CV's font. */
    public function fontFiles(): array
    {
        $files = [];
        foreach ([400, 600, 700] as $weight) {
            $files[$weight] = "{$this->look['font']}-{$weight}.woff2";
        }

        return $files;
    }

    /** "Lena Brandt – CV" or "Lena Brandt – Lebenslauf". */
    public function title(): string
    {
        return $this->name().' – '.($this->resume->language === 'de' ? 'Lebenslauf' : 'CV');
    }

    public function name(): string
    {
        return $this->user->name;
    }

    /** "Hamburg, Germany", or whichever part is known. */
    public function place(): string
    {
        return implode(', ', array_filter([$this->profile->city, Countries::name($this->profile->country)]));
    }

    /** @return list<string> Email, phone and place, where known. */
    public function contact(): array
    {
        return array_values(array_filter([$this->profile->email, $this->profile->phone, $this->place()]));
    }

    /** The photo as a data URI, so the page needs nothing else to show it. */
    public function photo(): ?string
    {
        $path = $this->profile->photo_path;
        if (! $this->look['photo'] || $path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) Storage::disk('local')->get($path));
    }

    /**
     * The sections to show, in order, skipping hidden and empty ones.
     *
     * @return list<array{key: string, title: string, content: string|Collection<int, covariant Model>}>
     */
    public function sections(): array
    {
        $titles = [
            'summary' => __('Profile'),
            'experiences' => __('Work experience'),
            'educations' => __('Education'),
            'skills' => __('Skills'),
            'languages' => __('Languages'),
            'projects' => __('Projects'),
            'certifications' => __('Certificates'),
            'links' => __('Links'),
        ];
        $sections = [];
        foreach ($this->look['sections'] as $key) {
            if (in_array($key, $this->look['hidden'], true)) {
                continue;
            }
            $content = match ($key) {
                'summary' => trim((string) $this->profile->summary),
                'experiences' => $this->profile->experiences()->get(),
                'educations' => $this->profile->educations()->get(),
                'skills' => $this->profile->skills()->get(),
                'languages' => $this->profile->languages()->get(),
                'projects' => $this->profile->projects()->get(),
                'certifications' => $this->profile->certifications()->get(),
                default => $this->profile->links()->get(),
            };
            if ($content !== '' && ! ($content instanceof Collection && $content->isEmpty())) {
                $sections[] = ['key' => $key, 'title' => $titles[$key], 'content' => $content];
            }
        }

        return $sections;
    }

    /** "Mar 2021 – Present" in English, "03/2021 – heute" in German. */
    public function period(?DateTimeInterface $from, ?DateTimeInterface $to, bool $ongoing = true): string
    {
        if ($from === null) {
            return $this->month($to);
        }

        return $this->month($from).' – '.($to !== null ? $this->month($to) : ($ongoing ? __('Present') : ''));
    }

    public function month(?DateTimeInterface $date): string
    {
        return $date !== null ? Carbon::instance($date)->translatedFormat(App::getLocale() === 'de' ? 'm/Y' : 'M Y') : '';
    }

    /** "Hamburg, 7 November 2026", above the signature line. */
    public function signedAt(): string
    {
        $date = now()->translatedFormat(App::getLocale() === 'de' ? 'j. F Y' : 'j F Y');

        return implode(', ', array_filter([$this->profile->city, $date]));
    }
}
