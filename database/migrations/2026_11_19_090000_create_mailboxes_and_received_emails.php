<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The candidate's mailbox, checked for employers' replies, and the replies that matched one
 * of their applications. Emails that match nothing aren't kept. Sent application emails get
 * their Message-ID, so replies to them are found by their thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            // ssl, starttls, or none (only for a test server on this machine).
            $table->string('security', 10);
            $table->string('username', 255);
            // Encrypted with the app key.
            $table->text('password');
            // The highest message UID already looked at, so each check reads only new emails.
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->timestamp('checked_at')->nullable();
            // Why the last check failed, shown in the settings until a check works again.
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('received_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('message_id', 255);
            $table->string('from_address', 255);
            $table->string('from_name', 255);
            $table->string('subject', 500);
            $table->text('body');
            // thread, sender or subject: what tied it to the application.
            $table->string('matched_by', 10);
            $table->timestamp('received_at');
            $table->timestamp('created_at');
            $table->unique(['application_id', 'message_id']);
        });

        Schema::table('application_emails', function (Blueprint $table) {
            $table->string('message_id', 255)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('application_emails', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
        Schema::dropIfExists('received_emails');
        Schema::dropIfExists('mailboxes');
    }
};
