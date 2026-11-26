<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\ReceivedEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the candidate an employer replied, what the AI read it as and what changed, with the
 * interview as a calendar file when one was added. Plain values, so it is still sent if the
 * application is deleted meanwhile; the interview is looked up when the email is written, in
 * the candidate's language.
 */
final class ReplyRead extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $applicationId,
        public string $title,
        public string $company,
        public string $from,
        public string $subject,
        public ?string $movedTo,
        public ?int $interviewId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kind = ReceivedEmail::kindNames()[$this->kind] ?? $this->kind;
        $interview = $this->interviewId !== null ? ApplicationInterview::query()->find($this->interviewId) : null;
        $message = (new MailMessage)
            ->subject(__(':kind from :company', ['kind' => $kind, 'company' => $this->company !== '' ? $this->company : $this->from]))
            ->markdown('mail.reply-read', [
                'kind' => $kind,
                'invite' => $this->kind === 'interview',
                'title' => $this->title,
                'company' => $this->company,
                'from' => $this->from,
                'subject' => $this->subject,
                'movedTo' => $this->movedTo !== null ? Application::statusNames()[$this->movedTo] : null,
                'interview' => $interview,
                'url' => route('applications.show', ['id' => $this->applicationId]),
            ]);

        return $interview !== null ? $message->attachData($interview->ics(), 'interview.ics', ['mime' => 'text/calendar']) : $message;
    }
}
