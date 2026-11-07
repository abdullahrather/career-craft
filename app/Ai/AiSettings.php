<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Which AI provider and model the app uses, and the limits and prices around it. The
 * admin changes these in the app; API keys stay in .env and are never stored here.
 */
final readonly class AiSettings
{
    /** The providers the app supports, as named in config/ai.php. */
    public const array PROVIDERS = ['ollama', 'anthropic', 'openai-compatible'];

    private const string KEY = 'ai';

    /**
     * @param  array<string, string>  $models  Provider => model.
     * @param  array<string, array{float, float}>  $prices  Provider => US dollars per million input and output tokens.
     */
    public function __construct(
        public string $provider = 'ollama',
        public array $models = [],
        public string $compatibleUrl = '',
        public int $timeout = 120,
        public int $dailyTokens = 0,
        public array $prices = [],
    ) {}

    public static function current(): self
    {
        /** @var array<string, mixed> $saved */
        $saved = Cache::rememberForever('settings.'.self::KEY, function (): array {
            $value = DB::table('app_settings')->where('key', self::KEY)->value('value');

            return is_string($value) ? (array) json_decode($value, true) : [];
        });

        return self::fromArray($saved);
    }

    /** @param  array<string, mixed>  $saved */
    public static function fromArray(array $saved): self
    {
        $provider = $saved['provider'] ?? null;
        $models = is_array($saved['models'] ?? null) ? $saved['models'] : [];
        $prices = is_array($saved['prices'] ?? null) ? $saved['prices'] : [];
        $number = fn (mixed $value, int $default): int => is_numeric($value) ? (int) $value : $default;

        return new self(
            provider: is_string($provider) && in_array($provider, self::PROVIDERS, true) ? $provider : 'ollama',
            models: array_map(fn (mixed $model): string => is_string($model) ? $model : '', array_intersect_key($models, array_flip(self::PROVIDERS))),
            compatibleUrl: is_string($saved['compatibleUrl'] ?? null) ? $saved['compatibleUrl'] : '',
            timeout: $number($saved['timeout'] ?? null, 120),
            dailyTokens: $number($saved['dailyTokens'] ?? null, 0),
            prices: array_map(
                fn (mixed $pair): array => is_array($pair) ? [self::money($pair[0] ?? null), self::money($pair[1] ?? null)] : [0.0, 0.0],
                array_intersect_key($prices, array_flip(self::PROVIDERS)),
            ),
        );
    }

    public function save(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($this->toArray()), 'updated_at' => now(), 'created_at' => now()],
        );
        Cache::forget('settings.'.self::KEY);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'models' => $this->models,
            'compatibleUrl' => $this->compatibleUrl,
            'timeout' => $this->timeout,
            'dailyTokens' => $this->dailyTokens,
            'prices' => $this->prices,
        ];
    }

    private static function money(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** The model for the chosen provider, or the provider's usual one. */
    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider;
        $model = $this->models[$provider] ?? '';

        return $model !== '' ? $model : match ($provider) {
            'ollama' => config()->string('ai.providers.ollama.models.text.default', 'qwen2.5:7b'),
            'anthropic' => 'claude-sonnet-5-5',
            default => '',
        };
    }

    /** @return array{float, float} US dollars per million input and output tokens. */
    public function price(?string $provider = null): array
    {
        return $this->prices[$provider ?? $this->provider] ?? [0.0, 0.0];
    }

    /** Whether a provider has what it needs: a key in .env, or an address. */
    public function ready(string $provider): bool
    {
        return match ($provider) {
            'anthropic' => filled(config('ai.providers.anthropic.key')),
            'openai-compatible' => $this->compatibleUrl !== '' && $this->model('openai-compatible') !== '',
            default => true,
        };
    }
}
