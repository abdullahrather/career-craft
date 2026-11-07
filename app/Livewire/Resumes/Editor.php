<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * How a CV looks and what it shows. Every change is saved at once, and the preview
 * reloads to show it.
 */
final class Editor extends Component
{
    #[Locked]
    public int $resumeId;

    public string $name = '';

    public string $language = 'en';

    public string $template = 'classic';

    public string $font = '';

    public string $accent = '';

    public int $pages = 2;

    /** @var list<string> */
    public array $sections = [];

    /** @var list<string> */
    public array $hidden = [];

    public bool $photo = false;

    public bool $signature = false;

    public function mount(int $resumeId): void
    {
        $this->resumeId = $resumeId;
        $resume = $this->resume();
        $look = $resume->look();
        $this->name = $resume->name;
        $this->language = $resume->language;
        $this->template = $resume->template;
        $this->font = $look['font'];
        $this->accent = $look['accent'];
        $this->pages = $look['pages'];
        $this->sections = $look['sections'];
        $this->hidden = $look['hidden'];
        $this->photo = $look['photo'];
        $this->signature = $look['signature'];
    }

    public function updated(string $property): void
    {
        // A new template brings its own font, colour, photo and signature; the person can change them again.
        if ($property === 'template' && array_key_exists($this->template, Resume::TEMPLATES)) {
            $defaults = Resume::defaults($this->template);
            $this->font = $defaults['font'];
            $this->accent = $defaults['accent'];
            $this->photo = $defaults['photo'];
            $this->signature = $defaults['signature'];
        }
        $this->save();
    }

    /** Shows or hides a section. */
    public function toggle(string $section): void
    {
        abort_unless(in_array($section, Resume::SECTIONS, true), 404);
        $this->hidden = in_array($section, $this->hidden, true)
            ? array_values(array_diff($this->hidden, [$section]))
            : [...$this->hidden, $section];
        $this->save();
    }

    /** Moves a section up (-1) or down (+1). */
    public function move(string $section, int $direction): void
    {
        $index = array_search($section, $this->sections, true);
        abort_if($index === false, 404);
        $other = $index + ($direction < 0 ? -1 : 1);
        if (! isset($this->sections[$other])) {
            return;
        }
        $sections = $this->sections;
        [$sections[$index], $sections[$other]] = [$sections[$other], $sections[$index]];
        $this->sections = array_values($sections);
        $this->save();
    }

    public function render(): View
    {
        return view('livewire.resumes.editor', [
            'resume' => $this->resume(),
            'hasPhoto' => $this->resume()->user->careerProfile()->photo_path !== null,
        ]);
    }

    private function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::in(Resume::LANGUAGES)],
            'template' => ['required', Rule::in(array_keys(Resume::TEMPLATES))],
            'font' => ['required', Rule::in(array_keys(Resume::FONTS))],
            'accent' => ['required', Rule::in(Resume::ACCENTS)],
            'pages' => ['required', Rule::in([1, 2])],
            'sections' => ['array'],
            'sections.*' => [Rule::in(Resume::SECTIONS)],
            'hidden' => ['array'],
            'hidden.*' => [Rule::in(Resume::SECTIONS)],
            'photo' => ['boolean'],
            'signature' => ['boolean'],
        ], attributes: ['name' => __('Name')]);

        $this->resume()->update([
            'name' => trim($this->name),
            'language' => $this->language,
            'template' => $this->template,
            'settings' => [
                'font' => $this->font,
                'accent' => $this->accent,
                'pages' => $this->pages,
                'sections' => $this->sections,
                'hidden' => $this->hidden,
                'photo' => $this->photo,
                'signature' => $this->signature,
            ],
        ]);
        $this->dispatch('resume-saved');
    }

    private function resume(): Resume
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->resumes()->findOrFail($this->resumeId);
    }
}
