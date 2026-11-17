<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The person's applications as a board, from Saved to Offer or Rejected. A card moves by
 * dragging it, or with its status list, which works by keyboard too.
 */
final class Board extends Component
{
    public string $title = '';

    public string $company = '';

    public string $url = '';

    public string $source = '';

    /** Tracks a job found anywhere else. */
    public function add(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:250'],
            'company' => ['nullable', 'string', 'max:250'],
            'url' => ['nullable', 'url:http,https', 'max:2000'],
            'source' => ['nullable', 'string', 'max:100'],
        ], attributes: ['title' => __('Job title'), 'company' => __('Company'), 'url' => __('Link to the job'), 'source' => __('Found on')]);

        Application::track($this->user(), ['title' => trim($this->title), 'company' => trim($this->company), 'url' => $this->url !== '' ? $this->url : null, 'source' => trim($this->source)]);
        $this->reset('title', 'company', 'url', 'source');
    }

    /** A card dropped into a column, at a place in it. */
    public function move(int $id, int $position, string $status): void
    {
        abort_unless(in_array($status, Application::STATUSES, true), 422);
        $this->user()->applications()->findOrFail($id)->moveTo($status, $position);
    }

    /** A card moved with its status list, to the end of that column. */
    public function setStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, Application::STATUSES, true), 422);
        $this->user()->applications()->findOrFail($id)->moveTo($status);
    }

    public function render(): View
    {
        $applications = $this->user()->applications()->orderBy('position')->orderBy('id')->get()->groupBy('status');

        return view('livewire.applications.board', [
            'statuses' => Application::statusNames(),
            'columns' => array_map(fn (string $status) => $applications->get($status, collect()), array_combine(Application::STATUSES, Application::STATUSES)),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
