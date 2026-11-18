<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Mailbox;
use Carbon\CarbonInterface;

/** Reads a mailbox the way it was connected: over IMAP, or through the Gmail or Outlook API. */
final readonly class ProviderReader implements MailReader
{
    public function __construct(private ImapReader $imap, private GmailReader $gmail, private OutlookReader $outlook) {}

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
        return match ($mailbox->provider) {
            'gmail' => $this->gmail,
            'outlook' => $this->outlook,
            default => $this->imap,
        };
    }
}
