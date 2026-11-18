<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Mailbox;
use Carbon\CarbonInterface;

/** Reads the candidate's mailbox. */
interface MailReader
{
    /** Logs in and opens the inbox: why that failed, or null when it worked. */
    public function test(Mailbox $mailbox): ?string;

    /**
     * New emails in the inbox, by their headers, oldest first and at most $limit: those with a
     * UID above $afterUid, or when there is none yet, those received since $since.
     *
     * @return list<InboxMessage>
     *
     * @throws MailboxUnavailable
     */
    public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array;
}
