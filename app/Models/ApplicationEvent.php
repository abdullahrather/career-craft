<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something that happened to an application, for its timeline. It keeps plain facts, and
 * is worded when shown, in the reader's language.
 *
 * @property int $id
 * @property int $application_id
 * @property string $type
 * @property array<string, string|null> $data
 * @property Carbon $created_at
 */
#[Fillable(['type', 'data'])]
final class ApplicationEvent extends Model
{
    public const null UPDATED_AT = null;

    /** The event in a sentence. */
    public function text(): string
    {
        $statuses = Application::statusNames();
        $data = $this->data;

        return match ($this->type) {
            'added' => ($data['source'] ?? '') !== '' ? __('Added to Saved, found on :source', ['source' => $data['source']]) : __('Added to Saved'),
            'status' => __('Moved from :from to :to', ['from' => $statuses[$data['from'] ?? ''] ?? $data['from'], 'to' => $statuses[$data['to'] ?? ''] ?? $data['to']]),
            'documents' => match (true) {
                ($data['cv'] ?? null) !== null && ($data['letter'] ?? null) !== null => __('Recorded what was sent: :cv, and the letter “:letter”', ['cv' => $data['cv'], 'letter' => $data['letter']]),
                ($data['cv'] ?? null) !== null => __('Recorded what was sent: :cv', ['cv' => $data['cv']]),
                ($data['letter'] ?? null) !== null => __('Recorded what was sent: the letter “:letter”', ['letter' => $data['letter']]),
                default => __('Removed the record of what was sent'),
            },
            'note' => __('Added a note'),
            'contact' => __('Added :name as a contact', ['name' => $data['name'] ?? '']),
            'task' => __('Added the task “:title”, due :date', ['title' => $data['title'] ?? '', 'date' => Carbon::parse((string) ($data['due_on'] ?? 'today'))->translatedFormat('j M Y')]),
            'task_done' => __('Done: :title', ['title' => $data['title'] ?? '']),
            'interview' => __('Interview on :date', ['date' => Carbon::parse((string) ($data['starts_at'] ?? 'now'))->translatedFormat('j M Y, H:i')]),
            default => $this->type,
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
