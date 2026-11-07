<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved versions of a CV: the exact PDF and Word files, frozen when they were made, so
 * it's always clear what was sent out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            // 1, 2, 3 … within the CV.
            $table->unsignedInteger('number');
            // The CV's name, language and template when the version was saved.
            $table->string('name', 120);
            $table->string('language', 2);
            $table->string('template', 20);
            $table->string('pdf_path');
            $table->string('docx_path');
            // The PDF read back as plain text, as an applicant tracking system sees it.
            $table->longText('text');
            $table->unsignedSmallInteger('pages');
            // How many facts were checked, and which ones didn't come through.
            $table->unsignedSmallInteger('checked');
            $table->json('missing');
            $table->timestamps();

            $table->unique(['resume_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_versions');
    }
};
