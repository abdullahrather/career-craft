<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The career profile: one per person, and the single source every CV, letter and
 * match is built from. Entries keep a position so the person decides their order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->default('');
            $table->text('summary')->nullable();
            $table->string('email')->default('');
            $table->string('phone', 40)->default('');
            $table->string('city')->default('');
            $table->string('country', 2)->default('');
            $table->timestamps();
        });

        Schema::create('profile_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label', 60);
            $table->string('url', 500);
            $table->timestamps();
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title');
            $table->string('employer');
            $table->string('city')->default('');
            $table->string('country', 2)->default('');
            $table->date('starts_on');
            // Empty while the person still works there.
            $table->date('ends_on')->nullable();
            $table->text('description')->nullable();
            // One achievement per line, the bullet points a CV shows.
            $table->json('achievements')->default('[]');
            $table->timestamps();
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('institution');
            $table->string('degree');
            $table->string('field')->default('');
            $table->string('city')->default('');
            $table->string('country', 2)->default('');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('grade', 40)->default('');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name', 80);
            // 1 = basic to 4 = expert.
            $table->unsignedTinyInteger('level')->default(2);
            $table->timestamps();

            $table->unique(['profile_id', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->string('role')->default('');
            $table->string('url', 500)->default('');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('description')->nullable();
            $table->json('achievements')->default('[]');
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->string('issuer')->default('');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('url', 500)->default('');
            $table->timestamps();
        });

        Schema::create('profile_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name', 60);
            // A1 to C2 on the Common European Framework, or "native".
            $table->string('level', 6);
            $table->timestamps();

            $table->unique(['profile_id', 'name']);
        });
    }

    public function down(): void
    {
        foreach (['profile_languages', 'certifications', 'projects', 'skills', 'educations', 'experiences', 'profile_links', 'profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
