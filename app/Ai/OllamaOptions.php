<?php

declare(strict_types=1);

namespace App\Ai;

use Laravel\Ai\Enums\Lab;

/**
 * The options the agents send to a local Ollama model, and to no other provider: Claude
 * and OpenAI-compatible APIs take the options into the request as they are, and reject
 * fields they don't know.
 */
final class OllamaOptions
{
    /**
     * @param  int  $context  The context size. Ollama reloads the model whenever it changes, so
     *                        agents that run one after another share one size.
     * @return array<string, mixed>
     */
    public static function for(Lab|string $provider, int $context): array
    {
        if (($provider instanceof Lab ? $provider->value : $provider) !== Lab::Ollama->value) {
            return [];
        }

        // No thinking: newer models otherwise reason at length before every answer, which
        // makes each request many times slower. Models without it ignore the option.
        return ['num_ctx' => $context, 'think' => false];
    }
}
