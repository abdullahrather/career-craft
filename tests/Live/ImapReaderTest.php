<?php

declare(strict_types=1);

use App\Inbox\ImapReader;
use App\Inbox\MailboxUnavailable;
use App\Models\Mailbox;
use Illuminate\Support\Facades\Mail;

/*
 * The IMAP reader against GreenMail, the demo mailbox in compose.yaml. Not part of the
 * normal run or CI: `vendor/bin/pest tests/Live` inside Docker, with GreenMail running.
 * Skipped when GreenMail can't be reached.
 */

beforeEach(function (): void {
    $socket = @fsockopen('greenmail', 3143, $errno, $error, 2);
    if ($socket === false) {
        $this->markTestSkipped('GreenMail is not running.');
    }
    fclose($socket);
});

it('logs in, reads new emails by their headers, and their text only when asked', function (): void {
    $subject = 'Ihre Bewerbung '.uniqid();
    Mail::build(['transport' => 'smtp', 'host' => 'greenmail', 'port' => 3025, 'auto_tls' => false])
        ->raw("Hallo Lena,\n\nwir laden Sie gern ein.", fn ($message) => $message->from('jobs@nordwind-logistik.example', 'Nordwind Logistik')->to('demo@careercraft.test')->subject($subject));
    $reader = new ImapReader;
    $mailbox = new Mailbox(['host' => 'greenmail', 'port' => 3143, 'security' => 'none', 'username' => 'demo', 'password' => 'demo']);

    expect($reader->test($mailbox))->toBeNull()
        ->and($reader->test(new Mailbox([...$mailbox->only(['host', 'port', 'security', 'username']), 'password' => 'wrong'])))->toContain('didn\'t accept the user name or password');

    try {
        $reader->read(new Mailbox([...$mailbox->only(['host', 'port', 'security', 'username']), 'password' => 'wrong']), null, now()->subDay(), 200);
        $this->fail('A refused login must throw.');
    } catch (MailboxUnavailable $exception) {
        expect($exception->loginFailed)->toBeTrue();
    }

    $emails = collect($reader->read($mailbox, null, now()->subDay(), 200));
    $email = $emails->firstWhere('subject', $subject);
    expect($email)->not->toBeNull()
        ->and([$email->fromAddress, $email->fromName])->toBe(['jobs@nordwind-logistik.example', 'Nordwind Logistik'])
        ->and($email->messageId)->toEndWith('@nordwind-logistik.example')
        ->and(trim($email->body()))->toBe("Hallo Lena,\n\nwir laden Sie gern ein.")
        ->and($reader->read($mailbox, $emails->max('uid'), now()->subDay(), 200))->toBe([]);
});

it('reads by date again when the server numbered its emails afresh', function (): void {
    $subject = 'Ihre Bewerbung '.uniqid();
    Mail::build(['transport' => 'smtp', 'host' => 'greenmail', 'port' => 3025, 'auto_tls' => false])
        ->raw('Wir melden uns.', fn ($message) => $message->from('jobs@nordwind-logistik.example', 'Nordwind Logistik')->to('demo@careercraft.test')->subject($subject));
    $reader = new ImapReader;
    $mailbox = new Mailbox(['host' => 'greenmail', 'port' => 3143, 'security' => 'none', 'username' => 'demo', 'password' => 'demo']);
    $reader->read($mailbox, null, now()->subDay(), 200);
    $validity = $mailbox->uid_validity;
    expect($validity)->toBeGreaterThan(0);

    // UIDs far above any on the server: nothing is new while the numbering stays.
    $mailbox->last_uid = 999999;
    expect($reader->read($mailbox, 999999, now()->subDay(), 200))->toBe([])
        ->and($mailbox->last_uid)->toBe(999999);

    $mailbox->uid_validity = $validity + 1;
    expect(collect($reader->read($mailbox, 999999, now()->subDay(), 200))->pluck('subject'))->toContain($subject)
        ->and($mailbox->last_uid)->toBeNull()
        ->and($mailbox->uid_validity)->toBe($validity);
});
