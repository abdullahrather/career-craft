<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each employer's reply is, as the AI read it: a rejection, an invite, an offer and so on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_emails', function (Blueprint $table) {
            // One of ReceivedEmail::KINDS; empty until the AI has read the email.
            $table->string('kind', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('received_emails', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
