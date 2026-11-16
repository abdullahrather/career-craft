<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover letters for a job post, written by the AI from the post and the profile, then
 * checked by the fact guard and edited by the candidate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kept when the post or the CV is deleted: the letter stays.
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            // The CV sent with the letter, if any: its look styles the letter too.
            $table->foreignId('resume_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language', 2);
            // formal, warm or confident; short or standard.
            $table->string('tone', 10);
            $table->string('length', 10);
            // The address block, one line each, and the person to greet.
            $table->text('recipient');
            $table->string('contact', 120)->default('');
            $table->string('subject', 250)->default('');
            $table->text('body')->nullable();
            // What the fact guard found, and the quotes the candidate confirmed.
            $table->json('flags');
            $table->json('confirmed');
            $table->boolean('checked')->default(false);
            // writing, ready or failed.
            $table->string('status', 10);
            $table->string('error', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_letters');
    }
};
