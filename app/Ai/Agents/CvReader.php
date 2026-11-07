<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Copies the facts in a CV's text into the career profile's fields. The person checks
 * every field before anything is saved, and ProfileDraft cleans the answer first.
 */
#[Temperature(0)]
final class CvReader implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return Prompts::get('cv-reader');
    }

    /** Room for a long CV and the answer; Ollama's default context is too small. */
    public function providerOptions(Lab|string $provider): array
    {
        return ['num_ctx' => 16384];
    }

    public function schema(JsonSchema $schema): array
    {
        $text = fn () => $schema->string()->required();
        $list = fn () => $schema->array()->items($schema->string())->required();

        return [
            'personal' => $schema->object([
                'headline' => $text(),
                'summary' => $text(),
                'email' => $text(),
                'phone' => $text(),
                'city' => $text(),
                'country' => $text(),
            ])->required(),
            'experiences' => $schema->array()->items($schema->object([
                'title' => $text(),
                'employer' => $text(),
                'city' => $text(),
                'country' => $text(),
                'starts_on' => $text(),
                'ends_on' => $text(),
                'description' => $text(),
                'achievements' => $list(),
            ]))->required(),
            'educations' => $schema->array()->items($schema->object([
                'institution' => $text(),
                'degree' => $text(),
                'field' => $text(),
                'city' => $text(),
                'country' => $text(),
                'starts_on' => $text(),
                'ends_on' => $text(),
                'grade' => $text(),
            ]))->required(),
            'skills' => $schema->array()->items($schema->object(['name' => $text()]))->required(),
            'languages' => $schema->array()->items($schema->object([
                'name' => $text(),
                'level' => $schema->string()->enum(['', 'native', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'])->required(),
            ]))->required(),
            'projects' => $schema->array()->items($schema->object([
                'name' => $text(),
                'role' => $text(),
                'url' => $text(),
                'description' => $text(),
                'achievements' => $list(),
            ]))->required(),
            'certifications' => $schema->array()->items($schema->object([
                'name' => $text(),
                'issuer' => $text(),
                'issued_on' => $text(),
            ]))->required(),
            'links' => $schema->array()->items($schema->object([
                'label' => $text(),
                'url' => $text(),
            ]))->required(),
        ];
    }
}
