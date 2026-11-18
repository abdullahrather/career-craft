<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Mailbox;
use Illuminate\Support\Str;

/**
 * Checks a mailbox once: new emails are matched to the candidate's applications by their
 * headers, and only the matching ones are read and kept, on the application's page and
 * timeline. A mailbox that can't be read keeps the reason, for the settings, and whether the
 * server refused the login.
 */
final class MailboxCheck
{
    /** Longest email text kept; employers' replies are far shorter. */
    public const int BODY_LENGTH = 20000;

    public function __construct(private MailReader $reader) {}

    /** @return int How many emails were kept. */
    public function run(Mailbox $mailbox): int
    {
        $last = $mailbox->last_uid;
        $kept = 0;
        try {
            $messages = $this->reader->read($mailbox, $last, now()->subDays(Mailbox::FIRST_DAYS), Mailbox::BATCH);
            $matcher = new ReplyMatcher($mailbox->user()->firstOrFail());
            foreach ($messages as $message) {
                $match = $matcher->match($message);
                if ($match !== null) {
                    [$application, $matchedBy] = $match;
                    // Shortened once, so the check for an email already kept looks for what was saved.
                    $id = Str::limit($message->messageId !== '' ? $message->messageId : "uid-{$message->uid}@".($mailbox->host ?? $mailbox->provider), 255, '');
                    if (! $application->receivedEmails()->where('message_id', $id)->exists()) {
                        $application->receivedEmails()->create([
                            'message_id' => $id,
                            'from_address' => Str::limit($message->fromAddress, 255, ''),
                            'from_name' => Str::limit($message->fromName, 255, ''),
                            'subject' => Str::limit($message->subject, 500, ''),
                            'body' => Str::limit(trim($message->body()), self::BODY_LENGTH),
                            'matched_by' => $matchedBy,
                            'received_at' => $message->date,
                        ]);
                        $application->log('reply', ['from' => $message->fromName !== '' ? $message->fromName : $message->fromAddress, 'subject' => Str::limit($message->subject, 200)]);
                        $kept++;
                    }
                }
                // Only once the email is done with, so one whose text couldn't be read is tried again.
                $last = max($last ?? 0, $message->uid);
            }
        } catch (MailboxUnavailable $exception) {
            $mailbox->update(['last_uid' => $last, 'checked_at' => now(), 'error' => $exception->getMessage(), 'login_failed' => $exception->loginFailed]);

            return $kept;
        }
        $mailbox->update(['last_uid' => $last, 'checked_at' => now(), 'error' => null, 'login_failed' => false]);

        return $kept;
    }
}
