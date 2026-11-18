<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Mailbox;
use Carbon\CarbonInterface;

/** Reads a mailbox the way it was connected: over IMAP, or through the Gmail API. */
final readonly class ProviderReader implements MailReader
{
    public function __construct(private ImapReader $imap, private GmailReader $gmail) {}

    public function test(Mailbox $mailbox): ?string
    {
        return $this->reader($mailbox)->test($mailbox);
    }

    public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
    {
        return $this->reader($mailbox)->read($mailbox, $afterUid, $since, $limit);
    }

    private function reader(Mailbox $mailbox): MailReader
    {
        return $mailbox->provider === 'gmail' ? $this->gmail : $this->imap;
    }
}
