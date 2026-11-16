<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CVs tailored to a job post: the post a CV was made for, and the rewordings the AI
 * suggested for it, which the candidate accepts or rejects one by one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            // Kept when the post is deleted: the CV and its accepted changes stay.
            $table->foreignId('job_post_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('tailored_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            // The job whose bullet point this rewords; none for the summary.
            $table->foreignId('experience_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // The text as it was in the profile, and the AI's wording for this job.
            $table->text('original');
            $table->text('suggestion')->nullable();
            // What the fact guard found, whether it could run, and whether the candidate confirmed it.
            $table->json('flags');
            $table->boolean('checked')->default(false);
            $table->boolean('confirmed')->default(false);
            // waiting, ready, unchanged, failed, accepted or rejected.
            $table->string('status', 10);
            $table->string('error', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tailored_changes');
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_post_id');
        });
    }
};
