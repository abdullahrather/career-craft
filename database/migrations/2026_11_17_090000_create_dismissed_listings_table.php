<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs a person marked "not interested": hidden from them, and their titles rank similar
 * jobs lower. The title is kept, as the job itself is deleted after a month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissed_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 250);
            $table->timestamps();

            $table->unique(['user_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_listings');
    }
};
