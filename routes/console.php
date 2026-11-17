<?php

declare(strict_types=1);

use App\Jobs\ImportCompanyBoard;
use App\Models\FollowedBoard;
use App\Models\JobListing;
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

// Every day: each followed company board is fetched again, and jobs no source has shown for
// a month are deleted. The scheduler service in compose.yaml runs it.
Artisan::command('careercraft:refresh-jobs', function (): int {
    $boards = FollowedBoard::query()->select(['board', 'slug'])->distinct()->get();
    foreach ($boards as $followed) {
        ImportCompanyBoard::dispatch($followed->board, $followed->slug);
    }
    $deleted = JobListing::query()->where('last_seen_at', '<', now()->subDays(JobListing::KEEP_DAYS))->toBase()->delete();
    $this->info("Refreshing {$boards->count()} company boards. Deleted {$deleted} old jobs.");

    return 0;
})->purpose('Fetch followed company boards again, and delete old jobs');

Schedule::command('careercraft:refresh-jobs')->dailyAt('06:00');
