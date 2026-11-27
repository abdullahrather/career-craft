<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An email from the candidate's mailbox that matched one of their applications, as text.
 *
 * @property int $id
 * @property int $application_id
 * @property string $message_id
 * @property string $from_address
 * @property string $from_name
 * @property string $subject
 * @property string $body
 * @property string $matched_by thread, sender or subject.
 * @property string|null $kind One of KINDS, or null until the AI has read it.
 * @property string|null $moved_from The column the application was in before this email moved it.
 * @property int|null $interview_id The interview this email added.
 * @property Carbon|null $corrected_at When the candidate labelled it by hand.
 * @property Carbon $received_at
 * @property Carbon $created_at
 * @property-read Application $application
 * @property-read ApplicationInterview|null $interview
 */
#[Fillable(['message_id', 'from_address', 'from_name', 'subject', 'body', 'matched_by', 'received_at', 'kind'])]
final class ReceivedEmail extends Model
{
    public const null UPDATED_AT = null;

    /** What a reply can be. "other" is anything else, such as a note that the application arrived. */
    public const array KINDS = ['rejection', 'interview', 'assessment', 'information', 'offer', 'other'];

    /** How many of the candidate's latest labels a new email is compared with. */
    public const int CORRECTIONS = 50;

    /** The share of word triples two emails have in common to count as the same email. */
    public const float ALIKE = 0.8;

    /** @return array<string, string> */
    public static function kindNames(): array
    {
        return ['rejection' => __('Rejection'), 'interview' => __('Interview invite'), 'assessment' => __('Assessment'), 'information' => __('Asks for information'), 'offer' => __('Offer'), 'other' => __('Other')];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<ApplicationInterview, $this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(ApplicationInterview::class);
    }

    /**
     * The latest email the candidate labelled by hand that says the same as this one, such
     * as the same template sent for another job, so its label can be used again. A local AI
     * model doesn't reliably follow such labels as examples, so they are matched directly.
     */
    public function likeCorrected(): ?self
    {
        $triples = $this->triples();
        // Too short to tell apart: "Leider nicht." would match any short email.
        if (count($triples) < 5) {
            return null;
        }

        $corrections = self::query()->with('application')->whereNotNull('corrected_at')->whereKeyNot($this->id)
            ->whereRelation('application', 'user_id', $this->application->user_id)
            ->latest('corrected_at')->latest('id')->limit(self::CORRECTIONS)->get();

        return $corrections->first(function (self $correction) use ($triples): bool {
            $theirs = $correction->triples();

            return count(array_intersect_key($triples, $theirs)) / count($triples + $theirs) >= self::ALIKE;
        });
    }

    /**
     * The subject and text as runs of three words, without the company's name and the job
     * title, so one template reads the same for every job it is sent for.
     *
     * @return array<string, true>
     */
    private function triples(): array
    {
        $text = mb_strtolower($this->subject."\n".mb_substr($this->body, 0, 3000));
        $names = array_filter([trim($this->application->company), trim($this->application->title)], fn (string $name): bool => $name !== '');
        $text = str_replace(array_map(mb_strtolower(...), $names), ' ', $text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $triples = [];
        for ($i = 0; $i + 2 < count($words); $i++) {
            $triples["{$words[$i]} {$words[$i + 1]} {$words[$i + 2]}"] = true;
        }

        return $triples;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'corrected_at' => 'datetime'];
    }
}
