<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An import waiting for the person to confirm it (one per profile), and the CV photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->unique()->constrained()->cascadeOnDelete();
            // "cv" or "linkedin".
            $table->string('source', 10);
            $table->string('filename');
            // "reading", then "ready" or "failed".
            $table->string('status', 10)->default('reading');
            // The CV's text, kept only until the AI has read it.
            $table->longText('text')->nullable();
            // What was found, for the person to confirm.
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
        Schema::dropIfExists('profile_imports');
    }
};
