<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people behind an application, notes on it, and a timeline of what happened to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            // Such as recruiter or hiring manager.
            $table->string('role', 150)->default('');
            $table->string('email', 254)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('linkedin', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // added, status, documents, note or contact.
            $table->string('type', 20);
            // The plain facts, such as the old and new status; worded when shown.
            $table->json('data');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('application_contacts');
    }
};
