<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Feedback on the answers in a mock interview, written by the AI once it's over. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            // writing, ready or failed; empty until feedback is first asked for.
            $table->string('feedback_status', 10)->nullable();
            // The scores, feedback and stronger answer for each question answered.
            $table->json('feedback')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mock_interviews', fn (Blueprint $table) => $table->dropColumn(['feedback_status', 'feedback']));
    }
};
