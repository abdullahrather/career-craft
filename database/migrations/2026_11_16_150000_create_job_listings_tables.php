<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs imported from public job sources, shared by everyone. The same job found on two
 * sources is one listing with two sources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 250);
            $table->string('company', 250)->default('');
            $table->string('location', 250)->default('');
            // remote, hybrid, onsite, or empty when the source doesn't say.
            $table->string('work_mode', 10)->default('');
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            // year, month or hour.
            $table->string('salary_period', 10)->nullable();
            $table->text('description');
            $table->timestamp('posted_at')->nullable();
            // The title, company and place, normalised: how a job found twice is recognised.
            $table->string('fingerprint', 40)->unique();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });

        Schema::create('job_listing_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            // arbeitsagentur, arbeitnow or adzuna.
            $table->string('source', 20);
            $table->string('external_id', 191);
            $table->string('url', 2048);
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listing_sources');
        Schema::dropIfExists('job_listings');
    }
};
