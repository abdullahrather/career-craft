<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mail\GmailTransport;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Platform settings, such as the AI provider. Accounts become admins with `php artisan careercraft:admin`.
        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        // Applications sent through a candidate's Gmail, with their permission.
        Mail::extend('gmail', fn (array $config): GmailTransport => new GmailTransport(MailAccount::query()->whereKey($config['account'])->firstOrFail()));
    }
}
