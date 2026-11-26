<?php

declare(strict_types=1);

namespace App\Resumes;

use App\JobSearch\Skills;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\ProfileLanguage;
use App\Models\Project;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\TailoredChange;
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

    /** @var Collection<int, TailoredChange>|null The changes accepted for a tailored CV. */
    private ?Collection $accepted = null;

    /**
     * @param  bool  $tailored  Whether a CV made for a job shows its accepted changes and puts what
     *                          the job asks for first. Without, it shows the profile as it is.
     */
    public function __construct(public readonly Resume $resume, public readonly Profile $profile, public readonly User $user, public readonly bool $tailored = true)
    {
        $this->look = $resume->look();
    }

    public static function for(Resume $resume, bool $tailored = true): self
    {
        return new self($resume, $resume->user->careerProfile(), $resume->user, $tailored);
    }

    /**
     * The whole CV as an HTML page for the PDF service, in the CV's language. It loads its
     * fonts from files sent along with it (see fontFiles()).
     */
    public function render(): string
    {
        return $this->inLanguage(fn (): string => view('resumes.document', ['doc' => $this])->render());
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
                'summary' => $this->summary(),
                'experiences' => $this->experiences(),
                'educations' => $this->profile->educations()->get(),
                'skills' => $this->skills(),
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

    /**
     * The skills and languages the CV lists, for the match score.
     *
     * @return list<string>
     */
    public function listed(): array
    {
        $names = [];
        foreach ($this->sections() as $section) {
            if (in_array($section['key'], ['skills', 'languages'], true) && $section['content'] instanceof Collection) {
                foreach ($section['content'] as $entry) {
                    /** @var Skill|ProfileLanguage $entry */
                    $names[] = $entry->name;
                }
            }
        }

        return $names;
    }

    /** Everything else the CV shows, as plain text, for the match score. */
    public function text(): string
    {
        $lines = [];
        foreach ($this->sections() as $section) {
            $content = $section['content'];
            if (is_string($content)) {
                $lines[] = $content;

                continue;
            }
            foreach ($content as $entry) {
                $lines = [...$lines, ...match (true) {
                    $entry instanceof Experience => [$entry->title, $entry->employer, (string) $entry->description, ...$entry->achievements],
                    $entry instanceof Education => [$entry->degree, $entry->field, $entry->institution, (string) $entry->description],
                    $entry instanceof Project => [$entry->name, $entry->role, (string) $entry->description, ...$entry->achievements],
                    $entry instanceof Certification => [$entry->name, $entry->issuer],
                    default => [],
                }];
            }
        }

        return implode("\n", array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /** The summary, or its accepted rewording while the profile still has the summary it rewords. */
    private function summary(): string
    {
        $summary = trim((string) $this->profile->summary);
        $change = $this->acceptedChanges()->first(fn (TailoredChange $change): bool => $change->experience_id === null && $change->original === $summary);

        return $change->suggestion ?? $summary;
    }

    /**
     * The jobs, each with its accepted rewordings, and its bullet points that name a skill
     * the post asks for first. The rewordings are shown, never saved to the profile.
     *
     * @return Collection<int, Experience>
     */
    private function experiences(): Collection
    {
        $experiences = $this->profile->experiences()->get();
        $skills = $this->jobSkills();
        foreach ($experiences as $experience) {
            $changes = $this->acceptedChanges()->where('experience_id', $experience->id);
            $achievements = array_map(fn (string $line): string => $changes->first(fn (TailoredChange $change): bool => $change->original === $line)->suggestion ?? $line, $experience->achievements);
            $first = array_filter($achievements, fn (string $line): bool => array_any($skills, fn (string $skill): bool => Skills::in($skill, $line)));
            $experience->achievements = [...array_values($first), ...array_values(array_diff_key($achievements, $first))];
        }

        return $experiences;
    }

    /**
     * The skills, those the post asks for first.
     *
     * @return Collection<int, Skill>
     */
    private function skills(): Collection
    {
        $skills = $this->profile->skills()->get();
        $wanted = array_merge(...array_map(Skills::names(...), $this->jobSkills()));
        $first = $skills->filter(fn (Skill $skill): bool => array_intersect(Skills::names($skill->name), $wanted) !== []);

        return $first->merge($skills->diff($first))->values();
    }

    /** @return list<string> The skills the post asks for, when this is a tailored CV whose post still exists. */
    private function jobSkills(): array
    {
        $post = $this->tailored ? $this->resume->jobPost : null;

        return $post === null ? [] : [...$post->required_skills, ...$post->nice_skills];
    }

    /** @return Collection<int, TailoredChange> */
    private function acceptedChanges(): Collection
    {
        if (! $this->tailored) {
            return new Collection;
        }

        // Asked once: the sections are built more than once for one page.
        return $this->accepted ??= $this->resume->changes()->where('status', TailoredChange::ACCEPTED)->get();
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
