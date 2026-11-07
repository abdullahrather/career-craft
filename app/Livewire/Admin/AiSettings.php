<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Ai\Agents\ProviderCheck;
use App\Ai\AiRunner;
use App\Ai\AiSettings as Settings;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * The admin's AI settings: provider, model, timeout, the daily allowance per person and
 * token prices, a live test of the provider, and usage over the last 30 days.
 */
final class AiSettings extends Component
{
    public string $provider = 'ollama';

    /** @var array<string, string> */
    public array $models = [];

    public string $compatibleUrl = '';

    public int $timeout = 120;

    public int $dailyTokens = 0;

    /** @var array<string, array{0: float|int|string, 1: float|int|string}> Provider => [input, output] dollars per million tokens. */
    public array $prices = [];

    public bool $saved = false;

    public string $answer = '';

    public function mount(): void
    {
        Gate::authorize('admin');
        $settings = Settings::current();
        $this->provider = $settings->provider;
        $this->compatibleUrl = $settings->compatibleUrl;
        $this->timeout = $settings->timeout;
        $this->dailyTokens = $settings->dailyTokens;
        foreach (Settings::PROVIDERS as $provider) {
            $this->models[$provider] = $settings->model($provider);
            $this->prices[$provider] = $settings->price($provider);
        }
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $this->validate([
            'provider' => ['required', Rule::in(Settings::PROVIDERS)],
            'models.*' => ['nullable', 'string', 'max:100'],
            "models.{$this->provider}" => ['required', 'string', 'max:100'],
            'compatibleUrl' => ['nullable', 'url:http,https', 'max:255', Rule::requiredIf($this->provider === 'openai-compatible')],
            'timeout' => ['required', 'integer', 'between:10,180'],
            'dailyTokens' => ['required', 'integer', 'between:0,100000000'],
            'prices.*.*' => ['required', 'numeric', 'between:0,1000'],
        ], attributes: [
            "models.{$this->provider}" => __('Model'),
            'compatibleUrl' => __('Address of the API'),
            'timeout' => __('Timeout'),
            'dailyTokens' => __('Daily allowance per person'),
            'prices.*.*' => __('Price'),
        ]);

        $settings = $this->settings();
        if (! $settings->ready($this->provider)) {
            $this->addError('provider', __('This provider is not set up yet. See the note under it.'));

            return;
        }
        $settings->save();
        $this->saved = true;
    }

    /** Asks the saved provider for one sentence, shown word by word as it arrives. */
    public function test(AiRunner $ai): void
    {
        Gate::authorize('admin');
        $this->answer = '';
        $user = Auth::user();

        try {
            $this->answer = $ai->stream(new ProviderCheck, 'Are you working?', $user instanceof User ? $user : null, 'provider_check', function (string $text): void {
                $this->stream($text, name: 'answer');
            });
        } catch (Throwable $exception) {
            // Shown to the admin as it is: the provider's own message is the most useful.
            report($exception);
            $this->addError('test', __('The provider did not answer: :message', ['message' => $exception->getMessage()]));
        }
    }

    public function render(): View
    {
        $since = now()->subDays(30);

        return view('livewire.admin.ai-settings', [
            'ready' => array_combine(Settings::PROVIDERS, array_map(fn (string $provider): bool => $this->settings()->ready($provider), Settings::PROVIDERS)),
            'ollamaModels' => $this->ollamaModels(),
            'byPerson' => AiUsage::query()
                ->where('created_at', '>=', $since)
                ->select('user_id', DB::raw('count(*) as requests'), DB::raw('sum(input_tokens + output_tokens) as tokens'), DB::raw('sum(cost) as cost'))
                ->groupBy('user_id')
                ->orderByDesc('tokens')
                ->with('user:id,name,email')
                ->limit(50)
                ->get(),
            'byModel' => AiUsage::query()
                ->where('created_at', '>=', $since)
                ->select('provider', 'model', DB::raw('count(*) as requests'), DB::raw('sum(input_tokens + output_tokens) as tokens'), DB::raw('sum(cost) as cost'))
                ->groupBy('provider', 'model')
                ->orderByDesc('tokens')
                ->get(),
        ]);
    }

    /** The settings as entered, not yet saved. */
    private function settings(): Settings
    {
        return new Settings(
            provider: $this->provider,
            models: array_map(fn (mixed $model): string => trim((string) $model), $this->models),
            compatibleUrl: trim($this->compatibleUrl),
            timeout: $this->timeout,
            dailyTokens: $this->dailyTokens,
            prices: array_map(fn (array $pair): array => [(float) $pair[0], (float) $pair[1]], $this->prices),
        );
    }

    /** @return list<string> The models installed in Ollama, or none when it can't be reached. */
    private function ollamaModels(): array
    {
        try {
            $models = Http::timeout(2)->get(config()->string('ai.providers.ollama.url').'/api/tags')->json('models');
        } catch (Throwable) {
            return [];
        }

        return is_array($models) ? array_values(array_filter(array_map(fn (mixed $model): string => is_array($model) && is_string($model['name'] ?? null) ? $model['name'] : '', $models))) : [];
    }
}
