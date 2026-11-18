<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Http\Controllers\GmailConnectController;
use App\Inbox\MailboxCheck;
use App\Inbox\MailReader;
use App\Models\MailAccount;
use App\Models\Mailbox as Box;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The candidate's mailbox, read for employers' replies: over IMAP, or through their Gmail
 * sign-in (GmailMailboxController). Saving logs in first; only a mailbox that let
 * CareerCraft in is saved.
 */
final class Mailbox extends Component
{
    public string $boxHost = '';

    public string $boxPort = '993';

    public string $boxSecurity = 'ssl';

    public string $boxUsername = '';

    /** Left empty to keep the saved password. */
    public string $boxPassword = '';

    public function mount(): void
    {
        $user = $this->user();
        $mailbox = $user->mailbox;
        $this->fill($mailbox?->provider === 'imap'
            ? ['boxHost' => (string) $mailbox->host, 'boxPort' => (string) $mailbox->port, 'boxSecurity' => (string) $mailbox->security, 'boxUsername' => $mailbox->username]
            : ['boxUsername' => $user->email]);
    }

    public function save(MailReader $reader): void
    {
        $user = $this->user();
        $mailbox = $user->mailbox;
        $this->validate([
            'boxHost' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'boxPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'boxSecurity' => ['required', Rule::in(MailAccount::securities())],
            'boxUsername' => ['required', 'string', 'max:255'],
            'boxPassword' => [$mailbox?->provider !== 'imap' ? 'required' : 'nullable', 'string', 'max:500'],
        ], attributes: ['boxHost' => __('Mail server'), 'boxPort' => __('Port'), 'boxSecurity' => __('Encryption'), 'boxUsername' => __('User name'), 'boxPassword' => __('Password')]);
        $problem = Box::hostProblem($this->boxHost, (int) $this->boxPort);
        if ($problem !== null) {
            $this->addError('boxHost', $problem);

            return;
        }

        // An IMAP server replaces a Gmail connection, but only once its login worked.
        $settings = [
            'provider' => 'imap',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'host' => strtolower($this->boxHost),
            'port' => (int) $this->boxPort,
            'security' => $this->boxSecurity,
            'username' => trim($this->boxUsername),
            'password' => $this->boxPassword !== '' ? $this->boxPassword : $mailbox?->password,
        ];
        $error = $reader->test($user->mailbox()->make($settings));
        if ($error !== null) {
            $this->addError('boxTest', $error);

            return;
        }
        // Another server or account starts afresh; the same one carries on where it was.
        $same = $mailbox?->provider === 'imap' && $mailbox->host === $settings['host'] && $mailbox->username === $settings['username'];
        $user->mailbox()->updateOrCreate([], [...$settings, 'error' => null, 'login_failed' => false, ...($same ? [] : ['last_uid' => null, 'checked_at' => null])]);
        $this->reset('boxPassword');
        session()->flash('mailbox-saved', __('Connected. CareerCraft checks it every 15 minutes; the first check looks back :days days.', ['days' => Box::FIRST_DAYS]));
    }

    /** Checks the mailbox now, a few times an hour at most. */
    public function check(MailboxCheck $check): void
    {
        $mailbox = $this->user()->mailbox;
        if ($mailbox === null) {
            return;
        }
        $key = "mailbox-check:{$mailbox->id}";
        if (RateLimiter::tooManyAttempts($key, 4)) {
            $this->addError('boxTest', __('Checked a moment ago. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));

            return;
        }
        RateLimiter::hit($key, 3600);
        $kept = $check->run($mailbox);
        if ($mailbox->error === null) {
            session()->flash('mailbox-saved', trans_choice('{0} Checked. No new replies to your applications.|{1} Checked. One new reply, on its application.|[2,*] Checked. :count new replies, each on its application.', $kept, ['count' => $kept]));
        }
    }

    public function remove(): void
    {
        $this->user()->mailbox()->delete();
        $this->reset();
        $this->mount();
    }

    public function render(): View
    {
        return view('livewire.settings.mailbox', ['mailbox' => $this->user()->mailbox()->first(), 'securities' => MailAccount::securities(), 'gmail' => GmailConnectController::enabled()]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
