<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Interview questions the AI wrote from an application's job post, for practice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            // writing, ready or failed; empty until questions are first asked for.
            $table->string('questions_status', 10)->nullable();
            // Each question with what it tests.
            $table->json('questions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn(['questions_status', 'questions']));
    }
};
