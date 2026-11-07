<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI layer: who may change its settings, the settings themselves, and a record of
 * every AI request for usage, cost and limits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        // Settings the admin changes in the app. Secrets such as API keys stay in .env.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            // Kept when the person is deleted, so totals stay right.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // What it was for, such as "cv_import".
            $table->string('feature', 40);
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            // In US dollars, from the prices set when the request was made.
            $table->decimal('cost', 12, 6);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('app_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
