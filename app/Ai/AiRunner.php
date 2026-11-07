<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AiUsage;
use App\Models\User;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

/**
 * Every AI request in the app goes through here: it checks the person's daily limit,
 * uses the provider and model the admin chose, retries when the provider is briefly
 * unavailable, and records the tokens and cost.
 */
final class AiRunner
{
    /** Tries for a dropped connection, an overloaded provider or a rate limit. */
    private const int ATTEMPTS = 3;

    public function prompt(Agent $agent, string $prompt, ?User $user, string $feature): AgentResponse
    {
        $settings = $this->prepare($user);

        $response = retry(
            self::ATTEMPTS,
            fn (): AgentResponse => $agent->prompt($prompt, provider: $settings->provider, model: $settings->model(), timeout: $settings->timeout),
            fn (int $attempt): int => $attempt * 2000,
            fn (Throwable $exception): bool => self::retryable($exception),
        );
        $this->record($settings, $user, $feature, $response->usage);

        return $response;
    }

    /**
     * Hands each piece of the answer to $onText as it arrives, and returns the whole
     * answer. Not retried: part of it may already be on the person's screen.
     *
     * @param  callable(string): void  $onText
     */
    public function stream(Agent $agent, string $prompt, ?User $user, string $feature, callable $onText): string
    {
        $settings = $this->prepare($user);
        $stream = $agent->stream($prompt, provider: $settings->provider, model: $settings->model(), timeout: $settings->timeout);

        $text = '';
        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->delta;
                $onText($event->delta);
            }
        }
        $this->record($settings, $user, $feature, $stream->usage);

        return $text;
    }

    /** Tokens the person may still use today, or null when there's no limit. */
    public function remaining(User $user): ?int
    {
        $limit = AiSettings::current()->dailyTokens;

        return $limit > 0 ? max(0, $limit - AiUsage::tokensToday($user)) : null;
    }

    /** US dollars for a request's tokens, at the prices set for its provider. */
    public static function cost(AiSettings $settings, int $inputTokens, int $outputTokens): float
    {
        [$input, $output] = $settings->price();

        return round(($inputTokens * $input + $outputTokens * $output) / 1_000_000, 6);
    }

    private function prepare(?User $user): AiSettings
    {
        if ($user !== null && $this->remaining($user) === 0) {
            throw new AiLimitReached;
        }

        $settings = AiSettings::current();
        // The address of an OpenAI-compatible API is set in the app, not in .env.
        config(['ai.providers.openai-compatible.url' => $settings->compatibleUrl]);
        app(AiManager::class)->purge($settings->provider);

        return $settings;
    }

    private function record(AiSettings $settings, ?User $user, string $feature, ?Usage $usage): void
    {
        $input = $usage === null ? 0 : $usage->inputTokens;
        $output = $usage === null ? 0 : $usage->outputTokens;

        AiUsage::query()->create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'provider' => $settings->provider,
            'model' => $settings->model(),
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost' => self::cost($settings, $input, $output),
        ]);
    }

    private static function retryable(Throwable $exception): bool
    {
        // Running out of credit won't fix itself in a few seconds.
        return $exception instanceof FailoverableException && ! $exception instanceof InsufficientCreditsException;
    }
}
