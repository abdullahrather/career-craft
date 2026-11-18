<?php

declare(strict_types=1);

namespace App\Inbox;

use RuntimeException;
use Throwable;

/**
 * The mailbox couldn't be read; the message says why, for the candidate. A refused login is
 * told apart from a passing problem, such as a server that didn't answer.
 */
final class MailboxUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $loginFailed = false, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
