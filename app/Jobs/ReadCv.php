<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CvReader;
use App\Import\ProfileDraft;
use App\Models\ProfileImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
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

    /** A local model on a laptop can take a few minutes for a long CV. */
    public int $timeout = 240;

    public function __construct(public ProfileImport $import) {}

    public function handle(): void
    {
        $response = (new CvReader)->prompt("CV text:\n\n".$this->import->text);

        $this->import->update([
            'status' => ProfileImport::READY,
            'data' => ProfileDraft::normalise($response->toArray()),
            'text' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => ProfileImport::FAILED, 'text' => null]);
    }
}
