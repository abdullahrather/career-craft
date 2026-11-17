<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answers to the questions application forms ask: facts the candidate enters once on the
 * profile, and two answers the AI writes for each queued job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Euros a year, gross.
            $table->unsignedInteger('salary_expectation')->nullable();
            $table->string('notice_period', 100)->default('');
            $table->date('earliest_start')->nullable();
            $table->string('work_permit', 200)->default('');
        });

        Schema::table('apply_queue_items', function (Blueprint $table) {
            // writing, ready or failed; empty for jobs queued before answers were written.
            $table->string('answers_status', 10)->nullable();
            // Each answer with what the fact guard couldn't find in the profile.
            $table->json('answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('apply_queue_items', fn (Blueprint $table) => $table->dropColumn(['answers_status', 'answers']));
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['salary_expectation', 'notice_period', 'earliest_start', 'work_permit']));
    }
};
