<?php

declare(strict_types=1);

namespace App\Livewire\Letters;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\WriteCoverLetter;
use App\Models\CoverLetter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A cover letter to read, edit and download. Polled while the AI writes it. What the fact
 * guard flagged must be confirmed, or taken out of the text, before the PDF can be made.
 */
final class Editor extends Component
{
    #[Locked]
    public int $letterId;

    /** Whether the fields hold the letter's text: once it has been written. */
    #[Locked]
    public bool $loaded = false;

    public string $recipient = '';

    public string $contact = '';

    public string $subject = '';

    public string $body = '';

    public function mount(int $letterId): void
    {
        $this->letterId = $letterId;
        $this->takeText($this->letter());
    }

    public function save(): void
    {
        $letter = $this->letter();
        abort_unless($letter->status === CoverLetter::READY, 404);
        $this->validate([
            'recipient' => ['required', 'string', 'max:500'],
            'contact' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:6000'],
        ], attributes: ['recipient' => __('Recipient'), 'contact' => __('Contact person'), 'subject' => __('Subject'), 'body' => __('Letter')]);

        $letter->update(['recipient' => trim($this->recipient), 'contact' => trim($this->contact), 'subject' => trim($this->subject), 'body' => trim($this->body)]);
        $this->dispatch('letter-saved');
    }

    /** Marks one flagged phrase as true. */
    public function confirm(string $quote): void
    {
        $letter = $this->letter();
        abort_unless(in_array($quote, array_column($letter->flags, 'quote'), true), 404);
        $letter->update(['confirmed' => array_values(array_unique([...$letter->confirmed, $quote]))]);
    }

    /** Confirms a letter the fact guard couldn't check. */
    public function confirmUnchecked(): void
    {
        $letter = $this->letter();
        abort_unless($letter->status === CoverLetter::READY && ! $letter->checked, 404);
        $letter->update(['confirmed' => array_values(array_unique([...$letter->confirmed, CoverLetter::UNCHECKED]))]);
    }

    /** Writes the letter again, after a failure. */
    public function retry(AiRunner $ai): void
    {
        $letter = $this->letter();
        abort_unless($letter->status === CoverLetter::FAILED, 404);
        if ($ai->remaining($letter->user) === 0) {
            $this->addError('letter', (new AiLimitReached)->getMessage());

            return;
        }
        $letter->update(['status' => CoverLetter::WRITING, 'error' => null]);
        WriteCoverLetter::dispatch($letter);
    }

    public function delete(): void
    {
        $letter = $this->letter();
        $postId = $letter->job_post_id;
        $letter->delete();
        $postId !== null ? $this->redirectRoute('jobs.show', ['id' => $postId]) : $this->redirectRoute('jobs');
    }

    public function render(): View
    {
        $letter = $this->letter();
        if (! $this->loaded) {
            $this->takeText($letter);
        }

        return view('livewire.letters.editor', ['letter' => $letter]);
    }

    /** Puts the letter's text into the fields, once it has been written. */
    private function takeText(CoverLetter $letter): void
    {
        $this->recipient = $letter->recipient;
        $this->contact = $letter->contact;
        $this->subject = $letter->subject;
        $this->body = (string) $letter->body;
        $this->loaded = $letter->status === CoverLetter::READY;
    }

    /** One of the signed-in person's letters; anyone else's simply isn't found. */
    private function letter(): CoverLetter
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->coverLetters()->findOrFail($this->letterId);
    }
}
