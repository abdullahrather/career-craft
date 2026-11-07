<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
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

    public function delete(int $id): void
    {
        $this->user()->resumes()->findOrFail($id)->delete();
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
