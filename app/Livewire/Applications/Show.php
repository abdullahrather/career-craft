<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Applications\SentDocuments;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One application: the job, where it was found, its status and date, and the CV and letter
 * that went out. Recording them keeps a copy of each PDF with the application, so it shows
 * exactly what was sent even after the CV is deleted or the letter edited.
 */
final class Show extends Component
{
    #[Locked]
    public int $applicationId;

    public string $title = '';

    public string $company = '';

    public string $location = '';

    public string $url = '';

    public string $source = '';

    public string $status = 'saved';

    public string $appliedOn = '';

    /** The CV version sent, by id, or empty. */
    public string $cvVersion = '';

    /** The cover letter sent, by id, or empty. */
    public string $letter = '';

    public function mount(int $id): void
    {
        $application = $this->application($id);
        $this->applicationId = $application->id;
        $this->fill([
            ...$application->only(['title', 'company', 'location', 'source', 'status']),
            'url' => (string) $application->url,
            'appliedOn' => (string) $application->applied_on?->toDateString(),
            'cvVersion' => (string) $application->resume_version_id,
            'letter' => (string) $application->cover_letter_id,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:250'],
            'company' => ['nullable', 'string', 'max:250'],
            'location' => ['nullable', 'string', 'max:250'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Application::STATUSES)],
            'appliedOn' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], attributes: ['title' => __('Job title'), 'company' => __('Company'), 'location' => __('Place'), 'url' => __('Link to the job'), 'source' => __('Found on'), 'status' => __('Status'), 'appliedOn' => __('Applied on')]);

        $application = $this->application($this->applicationId);
        $application->update([
            'title' => trim($this->title),
            'company' => trim($this->company),
            'location' => trim($this->location),
            'url' => $this->url !== '' ? $this->url : null,
            'source' => trim($this->source),
            'applied_on' => $this->appliedOn !== '' ? $this->appliedOn : null,
        ]);
        if ($application->status !== $this->status) {
            $application->moveTo($this->status);
            $this->appliedOn = (string) $application->applied_on?->toDateString();
        }
        session()->flash('saved', __('Saved.'));
    }

    /** Keeps a copy of the chosen CV version and letter with the application. */
    public function recordDocuments(SentDocuments $documents): void
    {
        $version = $this->cvVersion !== '' ? $this->versions()->findOrFail((int) $this->cvVersion) : null;
        $letter = $this->letter !== '' ? $this->user()->coverLetters()->findOrFail((int) $this->letter) : null;
        $error = $documents->record($this->application($this->applicationId), $version, $letter);
        if ($error !== null) {
            $this->addError($error['field'], $error['message']);

            return;
        }
        session()->flash('recorded', __('Recorded. These copies stay with the application.'));
        $this->dispatch('application-changed');
    }

    public function delete(): void
    {
        $application = $this->application($this->applicationId);
        Storage::disk('local')->deleteDirectory(Application::directory($application->id));
        $application->delete();
        $this->redirectRoute('applications', navigate: true);
    }

    /** A note or contact added below changes the timeline. */
    #[On('application-changed')]
    public function refreshTimeline(): void {}

    public function render(): View
    {
        $application = $this->application($this->applicationId);
        $postId = $application->job_post_id;

        return view('livewire.applications.show', [
            'application' => $application,
            'events' => $application->events()->get(),
            'statuses' => Application::statusNames(),
            // The CV versions and letters made for this job come first.
            'versions' => $this->versions()->with('resume')->orderBy('created_at', 'desc')->get()
                ->sortBy(fn (ResumeVersion $version): int => $postId !== null && $version->resume->job_post_id === $postId ? 0 : 1),
            'letters' => $this->user()->coverLetters()->where('status', CoverLetter::READY)->get()
                ->sortBy(fn (CoverLetter $letter): int => $postId !== null && $letter->job_post_id === $postId ? 0 : 1),
        ]);
    }

    /** @return Builder<ResumeVersion> */
    private function versions(): Builder
    {
        return ResumeVersion::query()->whereHas('resume', fn (Builder $query) => $query->where('user_id', $this->user()->id));
    }

    private function application(int $id): Application
    {
        return $this->user()->applications()->findOrFail($id);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
