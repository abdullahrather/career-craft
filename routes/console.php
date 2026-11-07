<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
