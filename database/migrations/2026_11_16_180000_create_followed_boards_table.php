<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The company job boards each person follows, fetched again every day. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followed_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // greenhouse, lever, lever-eu or ashby.
            $table->string('board', 20);
            // The company's name in the board's link.
            $table->string('slug', 100);
            $table->string('name', 250);
            $table->timestamps();

            $table->unique(['user_id', 'board', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followed_boards');
    }
};
