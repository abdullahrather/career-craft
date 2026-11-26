<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use App\Models\ReceivedEmail;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Reads an employer's reply: what kind of email it is, and for an interview invite the time,
 * length and place it gives. ReadReply checks the answer before acting on it.
 */
#[Temperature(0)]
final class ReplyReader implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** The most email text the AI gets; the decisive lines of a reply come early. */
    public const int TEXT_LENGTH = 6000;

    public function instructions(): string
    {
        return Prompts::get('reply-reader');
    }

    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(ReceivedEmail::KINDS)->required(),
            'interview_at' => $schema->string()->required(),
            'minutes' => $schema->integer()->required(),
            'where' => $schema->string()->required(),
        ];
    }

    /**
     * The email as the AI gets it, with the day it arrived for dates such as "next Tuesday",
     * marked off so it reads as data. Its angle brackets are swapped for look-alikes, so no
     * tag in it, however split or cased, can close the block early.
     */
    public static function input(ReceivedEmail $email): string
    {
        $text = "Received: {$email->received_at->format('Y-m-d (l)')}\nFrom: {$email->from_name} <{$email->from_address}>\nSubject: {$email->subject}\n\n".mb_substr($email->body, 0, self::TEXT_LENGTH);

        return '<email>'.str_replace(['<', '>'], ['‹', '›'], $text).'</email>';
    }
}
