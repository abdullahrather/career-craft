<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\MailAccount;
use App\Models\Mailbox;
use Carbon\CarbonInterface;
use DirectoryTree\ImapEngine\Connection\ImapConnection;
use DirectoryTree\ImapEngine\Exceptions\Exception as ImapException;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox as Imap;
use DirectoryTree\ImapEngine\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use ZBateson\MailMimeParser\Header\IHeaderPart;

/**
 * Reads a mailbox over IMAP with its user name and (app) password. Headers are fetched for
 * new emails; an email's text only when it's asked for. Emails stay unread on the server.
 */
final class ImapReader implements MailReader
{
    public function test(Mailbox $mailbox): ?string
    {
        try {
            $imap = $this->open($mailbox);
            $this->inbox($imap);
            $imap->disconnect();
        } catch (MailboxUnavailable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
    {
        $imap = $this->open($mailbox);
        try {
            $query = $this->inbox($imap)->messages()->withHeaders()->oldest()->limit($limit);
            // These add to the query's search in place.
            if ($afterUid !== null) {
                $query->uid($afterUid + 1, INF);
            } else {
                $query->since($since);
            }
            $messages = [];
            foreach ($query->get() as $message) {
                // "UID n:*" always answers with the newest email, even one seen before.
                if (! $message instanceof Message || ($afterUid !== null && $message->uid() <= $afterUid)) {
                    continue;
                }
                $messages[] = $this->message($message);
            }

            return $messages;
        } catch (ImapException $exception) {
            throw new MailboxUnavailable(__('Reading the mailbox failed: :reason', ['reason' => Str::limit($exception->getMessage(), 200)]), previous: $exception);
        }
    }

    private function message(Message $message): InboxMessage
    {
        $from = $message->from();
        $ids = fn (string $header): array => array_map(
            fn (IHeaderPart $part): string => trim((string) $part->getValue(), '<> '),
            $message->header($header)?->getParts() ?? [],
        );

        return new InboxMessage(
            uid: $message->uid(),
            messageId: trim((string) $message->messageId(), '<> '),
            fromAddress: Str::lower($from?->email() ?? ''),
            fromName: $from?->name() ?? '',
            subject: (string) $message->subject(),
            date: $message->date() ?? Carbon::now(),
            references: array_values(array_filter([...$ids('In-Reply-To'), ...$ids('References')])),
            body: function () use ($message): string {
                try {
                    $text = $message->text(fetch: true) ?? strip_tags((string) $message->html(fetch: true));

                    // Emails break lines with CRLF; kept text uses plain newlines.
                    return trim(str_replace(["\r\n", "\r"], "\n", $text));
                } catch (ImapException $exception) {
                    throw new MailboxUnavailable(__('Reading the mailbox failed: :reason', ['reason' => Str::limit($exception->getMessage(), 200)]), previous: $exception);
                }
            },
        );
    }

    /**
     * Logs in. Outside development the name is looked up once, checked, and the connection
     * goes to that very address.
     */
    private function open(Mailbox $mailbox): Imap
    {
        $imap = new Imap([
            'host' => $mailbox->host,
            'port' => $mailbox->port,
            'encryption' => $mailbox->security === 'none' ? null : $mailbox->security,
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'timeout' => 15,
        ]);
        try {
            if (app()->environment('local', 'testing')) {
                $imap->connect();
            } else {
                $addresses = MailAccount::addresses($mailbox->host);
                $problem = Mailbox::problem($addresses, $mailbox->port);
                if ($problem !== null) {
                    throw new MailboxUnavailable($problem);
                }
                $imap->connect(new ImapConnection(new PinnedImapStream($addresses[0], $mailbox->host)));
            }
        } catch (ImapCommandException) {
            throw new MailboxUnavailable(__('The mail server didn\'t accept the user name or password. Many providers need an app password for this.'));
        } catch (ImapException $exception) {
            throw new MailboxUnavailable(__('CareerCraft couldn\'t reach this mail server: :reason', ['reason' => Str::limit($exception->getMessage(), 200)]), previous: $exception);
        }

        return $imap;
    }

    private function inbox(Imap $imap): FolderInterface
    {
        try {
            return $imap->inbox();
        } catch (ImapException $exception) {
            throw new MailboxUnavailable(__('The inbox couldn\'t be opened: :reason', ['reason' => Str::limit($exception->getMessage(), 200)]), previous: $exception);
        }
    }
}
