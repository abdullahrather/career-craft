<?php

declare(strict_types=1);

namespace App\Applications;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\ApplicationTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What needs the person's attention across their applications: tasks due, interviews coming
 * up, and applications that have gone quiet in Applied. The dashboard and the daily email
 * both read it.
 */
final readonly class FollowThrough
{
    public function __construct(private User $user, private Carbon $now) {}

    /**
     * Open tasks due by today, the most overdue first.
     *
     * @return Collection<int, ApplicationTask>
     */
    public function dueTasks(): Collection
    {
        return ApplicationTask::query()
            ->whereHas('application', fn (Builder $query) => $query->where('user_id', $this->user->id))
            ->whereNull('done_at')
            ->whereDate('due_on', '<=', $this->now->toDateString())
            ->with('application')
            ->orderBy('due_on')->orderBy('id')
            ->get();
    }

    /**
     * Interviews from today on, for this many days.
     *
     * @return Collection<int, ApplicationInterview>
     */
    public function interviews(int $days): Collection
    {
        return ApplicationInterview::query()
            ->whereHas('application', fn (Builder $query) => $query->where('user_id', $this->user->id))
            ->where('starts_at', '>=', $this->now->copy()->startOfDay())
            ->where('starts_at', '<', $this->now->copy()->startOfDay()->addDays($days))
            ->with('application')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Applications in Applied with nothing on their timeline for the person's follow-up days,
     * and no open task: time to follow up. Each comes with when it last changed.
     *
     * @return Collection<int, array{application: Application, since: Carbon}>
     */
    public function quiet(): Collection
    {
        $threshold = $this->now->copy()->subDays($this->user->follow_up_days);

        return $this->user->applications()
            ->where('status', 'applied')
            ->whereDoesntHave('tasks', fn (Builder $query) => $query->whereNull('done_at'))
            ->withMax('events', 'created_at')
            ->orderBy('id')
            ->get()
            ->map(function (Application $application): array {
                $last = $application->getAttribute('events_max_created_at');

                return ['application' => $application, 'since' => is_string($last) ? Carbon::parse($last) : Carbon::instance($application->updated_at ?? $this->now)];
            })
            ->filter(fn (array $quiet): bool => $quiet['since']->lte($threshold))
            ->values();
    }
}
