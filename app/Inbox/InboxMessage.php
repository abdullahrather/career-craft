<?php

declare(strict_types=1);

namespace App\Inbox;

use Carbon\CarbonInterface;
use Closure;

/**
 * A new email in the candidate's mailbox, by its headers. Its text is read only when asked
 * for, which happens only for an email that matched an application.
 */
final readonly class InboxMessage
{
    /**
     * @param  list<string>  $references  The Message-IDs it replies to (In-Reply-To and References).
     * @param  Closure(): string  $body  Reads its text from the server.
     */
    public function __construct(
        public int $uid,
        public string $messageId,
        public string $fromAddress,
        public string $fromName,
        public string $subject,
        public CarbonInterface $date,
        public array $references,
        private Closure $body,
    ) {}

    public function body(): string
    {
        return ($this->body)();
    }
}
