<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks and interviews on applications, and the reminders that keep a search moving: how
 * long an application may stay quiet before a follow-up is suggested, and a daily email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->date('due_on');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // The time as entered, wherever the interview is: calendars show it at that hour.
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('minutes')->default(60);
            // An address, a phone number or a video link.
            $table->string('location', 500)->default('');
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            // When the daily email last suggested following up, so it says so once per quiet spell.
            $table->timestamp('nudged_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('follow_up_days')->default(7);
            $table->boolean('reminder_emails')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['follow_up_days', 'reminder_emails']));
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('nudged_at'));
        Schema::dropIfExists('application_interviews');
        Schema::dropIfExists('application_tasks');
    }
};
