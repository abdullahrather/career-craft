<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Things to do for an application by a day. Open tasks show on the dashboard when due. */
final class Tasks extends Component
{
    #[Locked]
    public int $applicationId;

    public string $task = '';

    public string $dueOn = '';

    public function mount(): void
    {
        $this->dueOn = today()->toDateString();
    }

    public function add(): void
    {
        $this->task = trim($this->task);
        $this->validate([
            'task' => ['required', 'string', 'max:200'],
            'dueOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], attributes: ['task' => __('Task'), 'dueOn' => __('Due on')]);

        $application = $this->application();
        $application->tasks()->create(['title' => $this->task, 'due_on' => $this->dueOn]);
        $application->log('task', ['title' => $this->task, 'due_on' => $this->dueOn]);
        $this->reset('task');
        $this->dispatch('application-changed');
    }

    /** Marks a task done, or open again. */
    public function toggle(int $id): void
    {
        $application = $this->application();
        $task = $application->tasks()->findOrFail($id);
        $task->update(['done_at' => $task->done_at === null ? now() : null]);
        if ($task->done_at !== null) {
            $application->log('task_done', ['title' => $task->title]);
            $this->dispatch('application-changed');
        }
    }

    public function remove(int $id): void
    {
        $this->application()->tasks()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.applications.tasks', ['tasks' => $this->application()->tasks()->get()]);
    }

    private function application(): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($this->applicationId);
    }
}
