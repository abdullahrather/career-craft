<?php

declare(strict_types=1);

use App\Applications\DailyReminders;
use App\Jobs\ImportCompanyBoard;
use App\Jobs\ImportJobListings;
use App\JobSearch\JobAlerts;
use App\Models\FollowedBoard;
use App\Models\JobListing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Admins change the platform's settings, such as which AI provider it uses.
Artisan::command('careercraft:admin {email} {--remove : Take admin rights away}', function (string $email): int {
    $user = User::query()->where('email', $email)->first();
    if ($user === null) {
        $this->error("There is no account for {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => ! $this->option('remove')])->save();
    $this->info($user->is_admin ? "{$email} is now an admin." : "{$email} is no longer an admin.");

    return 0;
})->purpose('Make an account an admin, or take admin rights away');

// Every day: each followed company board and each saved search is fetched again, and jobs
// no source has shown for a month are deleted. The scheduler service in compose.yaml runs it.
Artisan::command('careercraft:refresh-jobs', function (): int {
    $boards = FollowedBoard::query()->select(['board', 'slug'])->distinct()->get();
    foreach ($boards as $followed) {
        ImportCompanyBoard::dispatch($followed->board, $followed->slug);
    }
    $searches = SavedSearch::query()->select(['what', 'where'])->selectRaw('min(user_id) as user_id')->groupBy(['what', 'where'])->get();
    foreach ($searches as $search) {
        ImportJobListings::fetch($search->what, $search->where, $search->user_id);
    }
    $deleted = JobListing::query()->where('last_seen_at', '<', now()->subDays(JobListing::KEEP_DAYS))->toBase()->delete();
    $this->info("Refreshing {$boards->count()} company boards and {$searches->count()} saved searches. Deleted {$deleted} old jobs.");

    return 0;
})->purpose('Fetch followed company boards and saved searches again, and delete old jobs');

// The saved searches' new jobs, by email, an hour after the refresh has fetched them.
Artisan::command('careercraft:send-job-alerts', function (): int {
    $this->info('Sent '.JobAlerts::send(now()).' job alerts.');

    return 0;
})->purpose('Email saved searches their new jobs');

// The morning email on applications: tasks due, interviews coming up, follow-ups.
Artisan::command('careercraft:send-reminders', function (): int {
    $this->info('Sent '.DailyReminders::send(now()).' reminder emails.');

    return 0;
})->purpose('Email people their tasks, interviews and follow-ups for the day');

Schedule::command('careercraft:refresh-jobs')->dailyAt('06:00');
Schedule::command('careercraft:send-job-alerts')->dailyAt('07:00');
Schedule::command('careercraft:send-reminders')->dailyAt('07:30');
