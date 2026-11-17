<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The candidate's own mail account, to send applications from their address, and a log of
 * every application email sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            // ssl, starttls, or none (only for a local test server).
            $table->string('security', 10);
            $table->string('username', 255);
            // Encrypted with the app key.
            $table->text('password');
            $table->string('from_address', 255);
            $table->string('from_name', 255);
            // When a test email went through; nothing is sent before.
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('to', 255);
            $table->string('subject', 250);
            $table->text('body');
            // The names of the files attached, as sent.
            $table->json('attachments');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_emails');
        Schema::dropIfExists('mail_accounts');
    }
};
