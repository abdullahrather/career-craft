<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CvReader;
use App\Ai\AiRunner;
use App\Import\ProfileDraft;
use App\Models\ProfileImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI read an uploaded CV, then keeps what it found for the person to confirm.
 * The CV's text is deleted either way.
 */
#[DeleteWhenMissingModels]
final class ReadCv implements ShouldQueue
{
    use Queueable;

    /** A second try rarely helps a model that has failed once; the person can upload again. */
    public int $tries = 1;

    /** Room for three tries at the longest AI timeout the admin can set (180 seconds). */
    public int $timeout = 600;

    public function __construct(public ProfileImport $import) {}

    public function handle(AiRunner $ai): void
    {
        $response = $ai->prompt(new CvReader, "CV text:\n\n".$this->import->text, $this->import->profile->user, 'cv_import');

        $this->import->update([
            'status' => ProfileImport::READY,
            'data' => ProfileDraft::normalise($response instanceof StructuredAgentResponse ? $response->toArray() : []),
            'text' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => ProfileImport::FAILED, 'text' => null]);
    }
}
