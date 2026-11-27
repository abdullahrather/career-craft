<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mock interviews for an application: the questions planned and what was said. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // The language it is held in: the job post's, or the page's.
            $table->string('locale', 2);
            // The main questions, in the order asked.
            $table->json('questions');
            // What the interviewer and the candidate said, in order.
            $table->json('messages');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_interviews');
    }
};
