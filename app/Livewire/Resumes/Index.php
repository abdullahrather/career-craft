<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\Models\Resume;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** The person's CVs, and a new one from a template. */
final class Index extends Component
{
    public string $name = '';

    public string $language = 'en';

    public string $template = 'classic';

    public function mount(): void
    {
        $this->language = in_array(App::getLocale(), Resume::LANGUAGES, true) ? App::getLocale() : 'en';
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::in(Resume::LANGUAGES)],
            'template' => ['required', Rule::in(array_keys(Resume::TEMPLATES))],
        ], attributes: ['name' => __('Name'), 'language' => __('Language'), 'template' => __('Template')]);

        $resume = $this->user()->resumes()->create([
            'name' => trim($this->name),
            'language' => $this->language,
            'template' => $this->template,
            'settings' => Resume::defaults($this->template),
        ]);

        $this->redirectRoute('resumes.edit', ['id' => $resume->id]);
    }

    /** A copy of a CV's choices, to start a variant from. Its versions stay with the original. */
    public function duplicate(int $id): void
    {
        $resume = $this->user()->resumes()->findOrFail($id);
        $this->user()->resumes()->create([
            'name' => mb_substr(__(':name (copy)', ['name' => $resume->name]), 0, 120),
            'language' => $resume->language,
            'template' => $resume->template,
            'settings' => $resume->settings,
        ]);
    }

    /** Deletes the CV with its saved versions and their files. The profile stays. */
    public function delete(int $id): void
    {
        $this->user()->resumes()->findOrFail($id)->delete();
        Storage::disk('local')->deleteDirectory(ResumeVersion::directory($id));
    }

    public function render(): View
    {
        return view('livewire.resumes.index', ['resumes' => $this->user()->resumes()->get()]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
