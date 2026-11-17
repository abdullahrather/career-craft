<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs a person queued to apply to: each one is analysed and gets a tailored CV and a
 * letter, ready to review and approve. Approved and skipped ones stay as the log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apply_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            // The CV the tailored copy starts from, and the copy itself.
            $table->foreignId('base_resume_id')->nullable()->constrained('resumes')->nullOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cover_letter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            // Kept for the log, as the listing is deleted after a month.
            $table->string('title', 250);
            $table->string('company', 250)->default('');
            $table->string('tone', 20);
            // Why preparing stopped, when it did.
            $table->string('error', 100)->nullable();
            // When the tailored CV and letter were started; they may be deleted afterwards.
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            // A skipped job can be queued again; the skip stays in the log.
            $table->index(['user_id', 'job_listing_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('apply_resume_id')->nullable()->constrained('resumes')->nullOnDelete();
            $table->string('apply_tone', 20)->default('formal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apply_resume_id');
            $table->dropColumn('apply_tone');
        });
        Schema::dropIfExists('apply_queue_items');
    }
};
