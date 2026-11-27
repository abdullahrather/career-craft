<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\ReplyReader;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Models\ApplicationInterview;
use App\Models\ReceivedEmail;
use App\Notifications\ReplyRead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI read an employer's reply, then acts on it: the application moves along the
 * board, an interview with a stated time goes into its interviews, and the candidate gets an
 * email. A reply that is none of the kinds the app acts on, such as a confirmation that the
 * application arrived, is only labelled. The candidate can correct the label, which takes
 * back what the old one did and acts on the new one, and is used again for the same email
 * later, such as the same template for another job.
 */
#[DeleteWhenMissingModels]
final class ReadReply implements ShouldQueue
{
    use Queueable;

    /** Where each kind of reply moves the application. */
    public const array MOVES = ['rejection' => 'rejected', 'interview' => 'interviewing', 'assessment' => 'interviewing', 'offer' => 'offer'];

    /** The board's columns in the order an application goes through them; Rejected ends it. */
    private const array ORDER = ['saved', 'applied', 'interviewing', 'offer'];

    public int $tries = 1;

    /** Three tries at the longest AI timeout the admin can set. */
    public int $timeout = 600;

    public function __construct(public ReceivedEmail $email) {}

    public function handle(AiRunner $ai): void
    {
        $email = $this->email;
        $application = $email->application;
        try {
            $response = $ai->prompt(new ReplyReader, ReplyReader::input($email), $application->user, 'reply_reading');
        } catch (AiLimitReached) {
            // Left unread; the email still shows on the application.
            return;
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $answer = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
        $kind = in_array($answer['kind'] ?? null, ReceivedEmail::KINDS, true) ? $answer['kind'] : 'other';
        // The candidate's label on the same email before beats the AI's reading.
        $like = $email->likeCorrected();
        $kind = $like->kind ?? $kind;
        $email->update(['kind' => $kind]);
        if ($kind === 'other') {
            return;
        }
        $application->log('reply_kind', $like !== null ? ['kind' => $kind, 'like' => $like->subject] : ['kind' => $kind]);

        $movedTo = self::act($email, $answer);
        $application->user->notify(new ReplyRead($kind, $application->id, $application->title, $application->company, $email->from_name !== '' ? $email->from_name : $email->from_address, $email->subject, $movedTo, $email->interview_id));
    }

    /**
     * The candidate's own label for an email: what the old label did is taken back, the new
     * one is acted on, and the AI gets the email as an example from then on.
     */
    public static function correct(ReceivedEmail $email, string $kind): void
    {
        $email->application->log('reply_corrected', ['subject' => $email->subject, 'kind' => $kind]);
        self::undo($email);
        $email->kind = $kind;
        $email->corrected_at = now();
        self::act($email);
    }

    /**
     * Whether a reply may move the application: only forward along the board, so a late
     * invite doesn't undo an offer, and never out of Rejected, which a rejection reaches from
     * anywhere.
     */
    public static function movesForward(string $from, string $to): bool
    {
        if ($from === 'rejected' || $from === $to) {
            return false;
        }

        return $to === 'rejected' || (int) array_search($to, self::ORDER, true) > (int) array_search($from, self::ORDER, true);
    }

    /** Whether the text gives the hour as a time: "14:00", "14.30", "14 Uhr", "2 pm", "2:30 p.m.". */
    public static function statesHour(string $text, int $hour): bool
    {
        $twelve = $hour % 12 === 0 ? 12 : $hour % 12;
        $patterns = [
            // Not a date such as 10.12.2026.
            '/(?<!\d)0?'.$hour.'(?:[:.]\d{2}(?!\.?\d)|\s?(?:uhr|h)\b)/iu',
            '/(?<!\d)0?'.$twelve.'(?:[:.]\d{2})?\s?(?:'.($hour < 12 ? 'am|a\.m\.' : 'pm|p\.m\.').')/iu',
        ];

        return preg_match($patterns[0], $text) === 1 || preg_match($patterns[1], $text) === 1;
    }

    /**
     * Moves the application as the email's kind says, and adds the interview the AI's answer
     * gives, noting both on the email so a correction can take them back. Returns the column
     * it moved to.
     *
     * @param  array<mixed>  $answer
     */
    private static function act(ReceivedEmail $email, array $answer = []): ?string
    {
        $application = $email->application;
        $movedTo = self::MOVES[(string) $email->kind] ?? null;
        if ($movedTo !== null && self::movesForward($application->status, $movedTo)) {
            $email->moved_from = $application->status;
            $application->moveTo($movedTo);
        } else {
            $movedTo = null;
        }
        $email->interview_id = $email->kind === 'interview' ? self::interview($email, $answer)?->id : null;
        $email->save();

        return $movedTo;
    }

    /**
     * Takes back what the email's label did, as far as nothing changed it since: the
     * application goes back while it's still in the column the email moved it to, and the
     * interview it added is deleted.
     */
    private static function undo(ReceivedEmail $email): void
    {
        $application = $email->application;
        if ($email->moved_from !== null && $application->status === (self::MOVES[(string) $email->kind] ?? null)) {
            $application->moveTo($email->moved_from);
        }
        $email->interview?->delete();
        $email->moved_from = null;
        $email->interview_id = null;
    }

    /**
     * Adds the interview the email fixes a time for, when that time is after the email came,
     * within a year, and its hour is written in the email, so a time the AI made up is left
     * out.
     *
     * @param  array<mixed>  $answer
     */
    private static function interview(ReceivedEmail $email, array $answer): ?ApplicationInterview
    {
        $application = $email->application;
        $at = is_string($answer['interview_at'] ?? null) ? trim($answer['interview_at']) : '';
        if (! Carbon::canBeCreatedFromFormat($at, 'Y-m-d H:i')) {
            return null;
        }
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $at);
        if (! $startsAt instanceof Carbon || $startsAt->lt($email->received_at->copy()->startOfDay()) || $startsAt->gt($email->received_at->copy()->addYear())
            || ! self::statesHour($email->subject."\n".$email->body, $startsAt->hour)
            || $application->interviews()->where('starts_at', $startsAt)->exists()) {
            return null;
        }

        $minutes = is_int($answer['minutes'] ?? null) && $answer['minutes'] >= 15 && $answer['minutes'] <= 480 ? $answer['minutes'] : 60;
        $where = is_string($answer['where'] ?? null) ? Str::limit(trim($answer['where']), 500, '') : '';
        $interview = $application->interviews()->create(['starts_at' => $startsAt, 'minutes' => $minutes, 'location' => $where]);
        $application->log('interview', ['starts_at' => $interview->starts_at->format('Y-m-d H:i')]);

        return $interview;
    }
}
