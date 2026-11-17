<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mail\GmailTransport;
use App\Mail\OutlookTransport;
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

        // Applications sent through a candidate's Gmail or Outlook, with their permission.
        Mail::extend('gmail', fn (array $config): GmailTransport => new GmailTransport(self::account($config)));
        Mail::extend('outlook', fn (array $config): OutlookTransport => new OutlookTransport(self::account($config)));
    }

    /** @param  array<array-key, mixed>  $config */
    private static function account(array $config): MailAccount
    {
        $account = $config['account'] ?? null;
        abort_unless($account instanceof MailAccount, 500);

        return $account;
    }
}
