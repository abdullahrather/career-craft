<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job posts a person has pasted or linked, with what was read from each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Where the post came from, when it was linked rather than pasted.
            $table->string('url', 2048)->nullable();
            // "reading", then "ready" or "failed".
            $table->string('status', 10)->default('reading');
            // Why reading failed, as a reason code the page translates.
            $table->string('error', 30)->nullable();
            // The post's text: what the skills are checked against, and later what a CV is tailored to.
            $table->longText('text')->nullable();
            $table->string('title')->default('');
            $table->string('company')->default('');
            $table->string('location')->default('');
            // "onsite", "hybrid", "remote" or empty when the post doesn't say.
            $table->string('work_mode', 10)->default('');
            // "intern", "junior", "mid", "senior", "lead" or empty.
            $table->string('seniority', 10)->default('');
            // "en" or "de", worked out from the text.
            $table->string('language', 2)->default('en');
            $table->json('required_skills')->default('[]');
            $table->json('nice_skills')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};
