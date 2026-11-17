<section class="card flex flex-col gap-4" aria-labelledby="tasks">
    <h2 id="tasks" class="card-title">{{ __('Tasks') }}</h2>

    @if ($tasks->isNotEmpty())
        <ul class="flex flex-col gap-2 text-sm">
            @foreach ($tasks as $task)
                @php($overdue = $task->done_at === null && $task->due_on->lt(today()))
                <li wire:key="task-{{ $task->id }}" class="flex items-start justify-between gap-3">
                    <label class="flex min-w-0 items-start gap-2">
                        <input type="checkbox" class="mt-1" wire:click="toggle({{ $task->id }})" @checked($task->done_at !== null)>
                        <span class="wrap-break-word">
                            <span @class(['muted line-through' => $task->done_at !== null])>{{ $task->title }}</span>
                            <span @class(['block text-xs', 'font-semibold text-red-700 dark:text-red-400' => $overdue, 'muted' => ! $overdue])>
                                {{ $overdue ? __('Overdue since :date', ['date' => $task->due_on->translatedFormat('j M Y')]) : __('Due :date', ['date' => $task->due_on->translatedFormat('j M Y')]) }}
                            </span>
                        </span>
                    </label>
                    <button type="button" wire:click="remove({{ $task->id }})" class="link shrink-0 text-xs" aria-label="{{ __('Delete the task “:title”', ['title' => $task->title]) }}">{{ __('Delete') }}</button>
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="add" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem] sm:items-start">
        <x-wire.input model="task" :label="__('Task')" :hint="__('Such as: send a thank-you email')" />
        <x-wire.input model="dueOn" type="date" :label="__('Due on')" />
        <button type="submit" class="button self-start justify-self-start" wire:loading.attr="disabled" wire:target="add">{{ __('Add task') }}</button>
    </form>
</section>
