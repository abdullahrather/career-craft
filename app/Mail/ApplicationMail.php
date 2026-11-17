<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An application, or a test, sent from the candidate's own address: their text as plain
 * text, with the CV and letter attached.
 */
final class ApplicationMail extends Mailable
{
    /** @param  array<string, string>  $files  Attachment names, each with its path on the local disk. */
    public function __construct(private string $fromAddress, private string $fromName, private string $mailSubject, private string $text, private array $files = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->fromAddress, $this->fromName), replyTo: [new Address($this->fromAddress, $this->fromName)], subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.application-text', with: ['text' => $this->text]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (string $name, string $path): Attachment => Attachment::fromStorageDisk('local', $path)->as($name)->withMime('application/pdf'),
            array_keys($this->files),
            $this->files,
        );
    }
}
