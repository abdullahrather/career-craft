<?php

declare(strict_types=1);

namespace App\Applications;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\ApplicationTask;
use App\Models\User;
use App\Notifications\DailyReminder;
use Illuminate\Support\Carbon;

/**
 * Sends each person with reminder emails on what needs them today: tasks due, interviews
 * today and tomorrow, and applications gone quiet. A quiet application is mentioned once
 * per quiet spell, not every morning. Nothing to say, no email; only verified addresses.
 */
final class DailyReminders
{
    /** Gives back how many emails went out. */
    public static function send(Carbon $now): int
    {
        $sent = 0;
        $users = User::query()->whereNotNull('email_verified_at')->where('reminder_emails', true)->whereHas('applications')->lazyById(100);
        foreach ($users as $user) {
            $follow = new FollowThrough($user, $now);
            $tasks = $follow->dueTasks()->map(fn (ApplicationTask $task): array => [
                'title' => $task->title,
                'application' => $task->application->title,
                'url' => route('applications.show', ['id' => $task->application_id]),
                'due_on' => $task->due_on->toDateString(),
                'overdue' => $task->due_on->lt($now->copy()->startOfDay()),
            ]);
            $interviews = $follow->interviews(2)->map(fn (ApplicationInterview $interview): array => [
                'starts_at' => $interview->starts_at->format('Y-m-d H:i'),
                'title' => $interview->application->title,
                'company' => $interview->application->company,
                'location' => $interview->location,
                'url' => route('applications.show', ['id' => $interview->application_id]),
            ]);
            $quiet = $follow->quiet()->filter(fn (array $item): bool => $item['application']->nudged_at === null || $item['application']->nudged_at->lt($item['since']));
            if ($tasks->isEmpty() && $interviews->isEmpty() && $quiet->isEmpty()) {
                continue;
            }

            $user->notify(new DailyReminder(
                array_values($tasks->all()),
                array_values($interviews->all()),
                array_values($quiet->map(fn (array $item): array => [
                    'title' => $item['application']->title,
                    'company' => $item['application']->company,
                    'days' => (int) $item['since']->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()),
                    'url' => route('applications.show', ['id' => $item['application']->id]),
                ])->all()),
            ));
            // Without touching updated_at, which stands in for the last activity of an old application.
            Application::query()->whereKey($quiet->map(fn (array $item): int => $item['application']->id)->all())->toBase()->update(['nudged_at' => $now]);
            $sent++;
        }

        return $sent;
    }
}
