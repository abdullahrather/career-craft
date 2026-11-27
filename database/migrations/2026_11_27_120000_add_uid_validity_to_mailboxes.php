<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table): void {
            // The IMAP server's UIDVALIDITY when last_uid was taken; a new one means it renumbered its emails.
            $table->unsignedBigInteger('uid_validity')->nullable()->after('last_uid');
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table): void {
            $table->dropColumn('uid_validity');
        });
    }
};
