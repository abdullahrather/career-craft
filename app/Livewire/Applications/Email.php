<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Applications\EmailSender;
use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Sends the application by email from the candidate's own address, with the recorded CV and
 * letter attached, after they check the recipient, subject and text. Sent emails are listed.
 */
final class Email extends Component
{
    #[Locked]
    public int $applicationId;

    public string $emailTo = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public function mount(): void
    {
        $draft = EmailSender::draft($this->application());
        $this->fill(['emailTo' => $draft['to'], 'emailSubject' => $draft['subject'], 'emailBody' => $draft['body']]);
    }

    public function send(EmailSender $sender): void
    {
        $this->validate([
            'emailTo' => ['required', 'email', 'max:255'],
            'emailSubject' => ['required', 'string', 'max:250'],
            'emailBody' => ['required', 'string', 'max:10000'],
        ], attributes: ['emailTo' => __('To'), 'emailSubject' => __('Subject'), 'emailBody' => __('Text')]);

        $error = $sender->send($this->application(), trim($this->emailTo), trim($this->emailSubject), trim($this->emailBody));
        if ($error !== null) {
            $this->addError('send', $error);

            return;
        }
        session()->flash('emailed', __('Sent to :to.', ['to' => trim($this->emailTo)]));
        $this->dispatch('application-changed');
    }

    /** Documents recorded above can now be attached. */
    #[On('application-changed')]
    public function refresh(): void {}

    public function render(): View
    {
        $application = $this->application();

        return view('livewire.applications.email', [
            'application' => $application,
            'account' => $application->user->mailAccount,
            'emails' => $application->emails()->get(),
        ]);
    }

    private function application(): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($this->applicationId);
    }
}
