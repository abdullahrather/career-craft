<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const ACCOUNTS_MIGRATION = 'database/migrations/2026_11_07_015000_add_locale_and_optional_password_to_users_table.php';

/** Whether users.password accepts NULL, from PostgreSQL's catalogue. */
function passwordIsOptional(): bool
{
    $row = DB::selectOne("select is_nullable from information_schema.columns where table_name = 'users' and column_name = 'password'");

    return is_object($row) && ($row->is_nullable ?? null) === 'YES';
}

it('upgrades a database that was migrated before accounts', function (): void {
    // Back to the users table as the foundation release left it.
    Artisan::call('migrate:rollback', ['--path' => ACCOUNTS_MIGRATION]);
    expect(Schema::hasColumn('users', 'locale'))->toBeFalse()
        ->and(passwordIsOptional())->toBeFalse();

    Artisan::call('migrate', ['--path' => ACCOUNTS_MIGRATION]);

    expect(Schema::hasColumn('users', 'locale'))->toBeTrue()
        ->and(passwordIsOptional())->toBeTrue();
});
