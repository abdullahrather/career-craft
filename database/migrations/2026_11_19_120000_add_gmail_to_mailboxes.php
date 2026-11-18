<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mailbox can also be read through the candidate's Gmail sign-in, with read-only
 * permission, instead of IMAP with an app password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            // imap or gmail.
            $table->string('provider', 10)->default('imap')->after('user_id');
            $table->string('host', 255)->nullable()->change();
            $table->unsignedSmallInteger('port')->nullable()->change();
            $table->string('security', 10)->nullable()->change();
            $table->text('password')->nullable()->change();
            // Encrypted with the app key.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn(['provider', 'access_token', 'refresh_token', 'token_expires_at']);
        });
    }
};
