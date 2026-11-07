<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CVs built from the career profile. A CV keeps only how it looks and what it shows;
 * the content always comes from the profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // The language of the headings and dates: "en" or "de".
            $table->string('language', 2);
            $table->string('template', 20);
            // Font, colour, page target, section order and toggles.
            $table->json('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
