<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Searches a person saved on Find jobs. Each one emails the jobs found since it was last
 * checked, every day or every week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('what', 100)->default('');
            $table->string('where', 100)->default('');
            $table->boolean('remote')->default(true);
            $table->string('mode', 10)->default('');
            $table->boolean('salary')->default(false);
            $table->string('company', 250)->default('');
            // daily or weekly.
            $table->string('frequency', 10);
            // Jobs found after this are new to the next alert.
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
