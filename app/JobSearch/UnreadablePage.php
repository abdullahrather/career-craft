<?php

declare(strict_types=1);

namespace App\JobSearch;

use RuntimeException;

/** A job post's page couldn't be read. The message is a reason code the page translates. */
final class UnreadablePage extends RuntimeException
{
    /** Reason codes, each with a message on the jobs page. */
    public const array REASONS = ['bad-url', 'blocked-address', 'unreachable', 'not-a-page', 'too-large', 'no-text', 'limit', 'ai'];
}
