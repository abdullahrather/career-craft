<?php

declare(strict_types=1);

namespace App\Notifications;

use App\JobSearch\ListingFilters;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The new jobs of a saved search, best match first. It holds plain values only, so it is
 * still sent when the search is deleted while the email waits in the queue.
 */
final class JobAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, company: string, location: string, url: string, skills: list<string>}>  $jobs  The best few.
     * @param  int  $total  How many new jobs there are in all.
     */
    public function __construct(public ListingFilters $filters, public string $frequency, public array $jobs, public int $total) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $search = $this->filters->label();

        return (new MailMessage)
            ->subject(trans_choice('{1} One new job for “:search”|[2,*] :count new jobs for “:search”', $this->total, ['search' => $search]))
            ->markdown('mail.job-alert', [
                'search' => $search,
                'jobs' => $this->jobs,
                'total' => $this->total,
                'frequency' => $this->frequency,
                'url' => route('discover', $this->filters->parameters()),
            ]);
    }
}
