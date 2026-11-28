<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Http\UploadedFile;
use Laravel\Ai\AiManager;
use Laravel\Ai\Transcription;

/**
 * Turns a spoken answer into text with the Whisper server the admin set up, such as a local
 * speaches. Without one, the browser's own speech recognition is used instead.
 */
final class Whisper
{
    public static function enabled(): bool
    {
        return AiSettings::current()->whisperUrl !== '';
    }

    /** @param  string  $language  "de" or "en". */
    public function transcribe(UploadedFile $audio, string $language): string
    {
        $settings = AiSettings::current();
        config(['ai.providers.whisper.url' => $settings->whisperUrl]);
        app(AiManager::class)->purge('whisper');

        return trim(Transcription::of($audio)->language($language)->timeout($settings->timeout)->generate('whisper', $settings->whisperModel)->text);
    }
}
