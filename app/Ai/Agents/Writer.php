<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Suggests a better wording for one piece of text from the career profile: a bullet point,
 * a summary or a description. It makes bullet points stronger, rewrites a summary, fixes
 * the tone or translates. The candidate sees each suggestion as a diff and decides.
 *
 * One text per request: a small local model given several at once mixes them up.
 */
#[Temperature(0)]
final class Writer implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string  $tool  improve, rewrite, tone or translate, each with its own prompt.
     * @param  string  $language  The language to translate into, for the translate tool.
     */
    public function __construct(private string $tool, private string $language = '') {}

    /**
     * The text as the AI gets it, marked off so it reads as data. Except when translating,
     * it ends by naming the text's language: a small local model otherwise tends to answer
     * German bullet points in English.
     */
    public function input(string $text): string
    {
        $marked = '<text>'.str_replace(['<text>', '</text>'], '', $text).'</text>';
        if ($this->tool === 'translate') {
            return $marked;
        }
        $language = self::isGerman($text) ? 'German' : 'English';

        return $marked."\n\nThe text is in {$language}. Answer in {$language}.";
    }

    /** Whether text looks German: common German words, and letters English doesn't use. */
    public static function isGerman(string $text): bool
    {
        return self::germanSigns($text) >= 2;
    }

    /** How German text looks: common German words, plus one for letters English doesn't use. */
    public static function germanSigns(string $text): int
    {
        preg_match_all('/\b(der|die|das|den|dem|des|und|von|mit|für|im|zum|zur|nach|bei|auf|aus|um|ein|eine|einer|ist|wurde|sowie|ihres|ihrer|während)\b/iu', $text, $matches);
        // Not in capitals: "MIT" is a university, not "mit".
        $words = array_filter($matches[0], fn (string $word): bool => mb_strtoupper($word) !== $word);
        $letters = preg_match('/[äöüß]/iu', $text);

        return count($words) + (int) $letters;
    }

    public function instructions(): string
    {
        return match ($this->tool) {
            'improve' => Prompts::get('writing-improve'),
            'rewrite' => Prompts::get('writing-rewrite'),
            'tone' => Prompts::get('writing-tone'),
            'translate' => Prompts::get('writing-translate', ['language' => $this->language]),
            default => throw new InvalidArgumentException("There is no writing tool called {$this->tool}."),
        };
    }

    /**
     * The fact checker's context size. Ollama reloads the model whenever it changes, which
     * would add seconds to every suggestion it checks.
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['num_ctx' => 8192];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['text' => $schema->string()->required()];
    }
}
