<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The jobs a person tracks, from saved to an offer or a no. Each keeps its own copies of
 * the CV and letter sent, as a CV can be deleted and a letter edited afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 250);
            $table->string('company', 250)->default('');
            $table->string('location', 250)->default('');
            $table->string('url', 2048)->nullable();
            // Where the job was found, such as Arbeitnow or a company's website.
            $table->string('source', 100)->default('');
            // saved, applied, interviewing, offer or rejected.
            $table->string('status', 20)->default('saved');
            // The order within its column on the board.
            $table->unsignedInteger('position')->default(0);
            $table->date('applied_on')->nullable();
            $table->foreignId('resume_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cv_name', 250)->nullable();
            $table->string('cv_path')->nullable();
            $table->foreignId('cover_letter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('letter_name', 250)->nullable();
            $table->string('letter_path')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_post_id']);
            $table->index(['user_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
