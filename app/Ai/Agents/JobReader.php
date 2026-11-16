<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use App\Models\JobPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Reads a job post: the role, company, location, work mode, seniority, and the skills it
 * requires or would like. AnalyseJobPost keeps only skills the post really names.
 */
#[Temperature(0)]
final class JobReader implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return Prompts::get('job-reader');
    }

    /** Room for a long post and the answer; Ollama's default context is too small. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 16384);
    }

    public function schema(JsonSchema $schema): array
    {
        $list = fn () => $schema->array()->items($schema->string())->required();

        return [
            'title' => $schema->string()->required(),
            'company' => $schema->string()->required(),
            'location' => $schema->string()->required(),
            'work_mode' => $schema->string()->enum(['', ...JobPost::WORK_MODES])->required(),
            'seniority' => $schema->string()->enum(['', ...JobPost::SENIORITIES])->required(),
            'required_skills' => $list(),
            'nice_skills' => $list(),
        ];
    }

    /** The post as the AI gets it, marked off so it reads as data. */
    public static function input(string $text): string
    {
        return '<post>'.str_replace(['<post>', '</post>'], '', $text).'</post>';
    }
}
