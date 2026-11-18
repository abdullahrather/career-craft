<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Inbox\MailboxCheck;
use App\Models\Mailbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Checks one mailbox for employers' replies. One check per mailbox at a time. */
final class CheckMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $mailboxId) {}

    public function uniqueId(): string
    {
        return (string) $this->mailboxId;
    }

    public function handle(MailboxCheck $check): void
    {
        $mailbox = Mailbox::query()->find($this->mailboxId);
        if ($mailbox !== null) {
            $check->run($mailbox);
        }
    }
}
