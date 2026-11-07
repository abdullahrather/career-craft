<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Saves every valid field. An invalid one (say, an emptied name) keeps its saved
     * value and shows its error, without holding up the rest.
     */
    private function save(): void
    {
        $validator = Validator::make([
            'name' => $this->name,
            'language' => $this->language,
            'template' => $this->template,
            'font' => $this->font,
            'accent' => $this->accent,
            'pages' => $this->pages,
            'sections' => $this->sections,
            'hidden' => $this->hidden,
            'photo' => $this->photo,
            'signature' => $this->signature,
        ], [
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

        $this->resetErrorBag();
        $failed = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $this->addError($field, $messages[0]);
            // A failing "sections.0" counts against all of "sections".
            $failed[explode('.', $field)[0]] = true;
        }
        $resume = $this->resume();
        $look = $resume->look();
        $ok = fn (string $field): bool => ! isset($failed[$field]);

        $resume->update([
            'name' => $ok('name') ? trim($this->name) : $resume->name,
            'language' => $ok('language') ? $this->language : $resume->language,
            'template' => $ok('template') ? $this->template : $resume->template,
            'settings' => [
                'font' => $ok('font') ? $this->font : $look['font'],
                'accent' => $ok('accent') ? $this->accent : $look['accent'],
                'pages' => $ok('pages') ? $this->pages : $look['pages'],
                'sections' => $ok('sections') ? $this->sections : $look['sections'],
                'hidden' => $ok('hidden') ? $this->hidden : $look['hidden'],
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
