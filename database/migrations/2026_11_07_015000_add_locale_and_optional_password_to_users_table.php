<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Empty for people who only sign in with Google or GitHub.
            $table->string('password')->nullable()->change();
            // Emails go out in this language.
            $table->string('locale', 5)->default('en')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
            $table->string('password')->nullable(false)->change();
        });
    }
};
