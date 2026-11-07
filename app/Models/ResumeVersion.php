<?php

declare(strict_types=1);

namespace App\Models;

use App\Resumes\AtsCheck;
use App\Resumes\DocxWriter;
use App\Resumes\PdfRenderer;
use App\Resumes\ResumeDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A saved version of a CV: its PDF and Word files as they were when saved, and how
 * the PDF reads back as plain text.
 *
 * @property int $id
 * @property int $resume_id
 * @property int $number
 * @property string $name
 * @property string $language
 * @property string $template
 * @property string $pdf_path
 * @property string $docx_path
 * @property string $text
 * @property int $pages
 * @property int $checked
 * @property list<string> $missing
 * @property Carbon $created_at
 * @property-read Resume $resume
 */
#[Fillable(['number', 'name', 'language', 'template', 'pdf_path', 'docx_path', 'text', 'pages', 'checked', 'missing'])]
final class ResumeVersion extends Model
{
    /** Saves the CV as it is now. Throws when the PDF service can't be reached. */
    public static function saveFrom(Resume $resume): self
    {
        $doc = ResumeDocument::for($resume);
        $pdf = app(PdfRenderer::class)->render($doc);
        $docx = (new DocxWriter)->write($doc);
        $check = AtsCheck::run($pdf, $doc);

        // Two saves at once (a double click, a second tab) could pick the same number.
        // The lock makes them take turns; if a number is still taken, try the next one.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): self => self::store($resume, $pdf, $docx, $check));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Numbers the version, then saves its row and files together: when either fails,
     * neither is left behind.
     *
     * @param  array{text: string, pages: int, checked: int, missing: list<string>}  $check
     */
    private static function store(Resume $resume, string $pdf, string $docx, array $check): self
    {
        Resume::query()->whereKey($resume->id)->lockForUpdate()->first();
        $last = $resume->versions()->max('number');
        $number = (is_numeric($last) ? (int) $last : 0) + 1;
        // A random part, so a file name can't be guessed from the number.
        $base = self::directory($resume->id).'/'.$number.'-'.Str::random(16);

        $version = $resume->versions()->create([
            'number' => $number,
            'name' => $resume->name,
            'language' => $resume->language,
            'template' => $resume->template,
            'pdf_path' => "{$base}.pdf",
            'docx_path' => "{$base}.docx",
            'text' => $check['text'],
            'pages' => $check['pages'],
            'checked' => $check['checked'],
            'missing' => $check['missing'],
        ]);

        try {
            Storage::disk('local')->put("{$base}.pdf", $pdf);
            Storage::disk('local')->put("{$base}.docx", $docx);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(["{$base}.pdf", "{$base}.docx"]);
            throw $exception;
        }

        return $version;
    }

    /** Where a CV's version files are kept, so deleting the CV can remove them all. */
    public static function directory(int $resumeId): string
    {
        return "resume-versions/{$resumeId}";
    }

    /** @return BelongsTo<Resume, $this> */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /** "Lena-Brandt-CV-v3.pdf" or "Lena-Brandt-Lebenslauf-v3.docx". */
    public function filename(string $format): string
    {
        return Str::slug($this->resume->user->name).'-'.($this->language === 'de' ? 'Lebenslauf' : 'CV')."-v{$this->number}.{$format}";
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['missing' => 'array'];
    }
}
