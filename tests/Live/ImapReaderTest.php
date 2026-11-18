<?php

declare(strict_types=1);

use App\Inbox\ImapReader;
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

    $emails = collect($reader->read($mailbox, null, now()->subDay(), 200));
    $email = $emails->firstWhere('subject', $subject);
    expect($email)->not->toBeNull()
        ->and([$email->fromAddress, $email->fromName])->toBe(['jobs@nordwind-logistik.example', 'Nordwind Logistik'])
        ->and($email->messageId)->toEndWith('@nordwind-logistik.example')
        ->and(trim($email->body()))->toBe("Hallo Lena,\n\nwir laden Sie gern ein.")
        ->and($reader->read($mailbox, $emails->max('uid'), now()->subDay(), 200))->toBe([]);
});
