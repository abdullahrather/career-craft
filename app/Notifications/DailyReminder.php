<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The morning email: tasks due, interviews today and tomorrow, and applications to follow
 * up. Plain values only, so it is still sent if an application is deleted meanwhile.
 */
final class DailyReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, application: string, url: string, due_on: string, overdue: bool}>  $tasks
     * @param  list<array{starts_at: string, title: string, company: string, location: string, url: string}>  $interviews
     * @param  list<array{title: string, company: string, days: int, url: string}>  $quiet
     */
    public function __construct(public array $tasks, public array $interviews, public array $quiet) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your applications today'))
            ->markdown('mail.daily-reminder', ['tasks' => $this->tasks, 'interviews' => $this->interviews, 'quiet' => $this->quiet]);
    }
}
