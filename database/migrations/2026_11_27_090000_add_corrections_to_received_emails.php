<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a reply changed, so correcting its label can take it back, and when the candidate
 * labelled it by hand, so the AI can learn from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_emails', function (Blueprint $table) {
            // The column the application was in before this email moved it.
            $table->string('moved_from', 20)->nullable();
            $table->foreignId('interview_id')->nullable()->constrained('application_interviews')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('received_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interview_id');
            $table->dropColumn(['moved_from', 'corrected_at']);
        });
    }
};
