<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Applications\EmailSender;
use App\Http\Controllers\GmailConnectController;
use App\Http\Controllers\OutlookConnectController;
use App\Models\MailAccount as Account;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The candidate's own mail server, to send applications from their address. Saving sends a
 * test email to that address; only an account whose test went through can send.
 */
final class MailAccount extends Component
{
    public string $mailHost = '';

    public string $mailPort = '587';

    public string $mailSecurity = 'starttls';

    public string $mailUsername = '';

    /** Left empty to keep the saved password. */
    public string $mailPassword = '';

    public string $mailFrom = '';

    public string $mailName = '';

    public function mount(): void
    {
        $user = $this->user();
        $account = $user->mailAccount;
        $this->fill($account?->provider === 'smtp'
            ? ['mailHost' => (string) $account->host, 'mailPort' => (string) $account->port, 'mailSecurity' => (string) $account->security, 'mailUsername' => (string) $account->username, 'mailFrom' => $account->from_address, 'mailName' => $account->from_name]
            : ['mailFrom' => $user->email, 'mailName' => $user->name, 'mailUsername' => $user->email]);
    }

    public function save(EmailSender $sender): void
    {
        $user = $this->user();
        $account = $user->mailAccount;
        $this->validate([
            'mailHost' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'mailPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'mailSecurity' => ['required', Rule::in(Account::securities())],
            'mailUsername' => ['nullable', 'string', 'max:255'],
            'mailPassword' => [$account?->provider !== 'smtp' ? 'required' : 'nullable', 'string', 'max:500'],
            'mailFrom' => ['required', 'email', 'max:255'],
            'mailName' => ['required', 'string', 'max:255'],
        ], attributes: ['mailHost' => __('Mail server'), 'mailPort' => __('Port'), 'mailSecurity' => __('Encryption'), 'mailUsername' => __('User name'), 'mailPassword' => __('Password'), 'mailFrom' => __('Send from'), 'mailName' => __('Your name')]);
        $problem = Account::hostProblem($this->mailHost, (int) $this->mailPort);
        if ($problem !== null) {
            $this->addError('mailHost', $problem);

            return;
        }

        // A mail server replaces a Gmail connection, but only once its test went through: a
        // server that can't send leaves the account there was.
        $settings = [
            'provider' => 'smtp',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'host' => strtolower($this->mailHost),
            'port' => (int) $this->mailPort,
            'security' => $this->mailSecurity,
            'username' => trim($this->mailUsername),
            'password' => $this->mailPassword !== '' ? $this->mailPassword : $account?->password,
            'from_address' => trim($this->mailFrom),
            'from_name' => trim($this->mailName),
        ];
        $error = $sender->test($user->mailAccount()->make($settings));
        if ($error !== null) {
            $this->addError('test', $error);

            return;
        }
        $saved = $user->mailAccount()->updateOrCreate([], [...$settings, 'verified_at' => now()]);
        $this->reset('mailPassword');
        session()->flash('mail-saved', __('A test email went to :address. Applications can now be sent from it.', ['address' => $saved->from_address]));
    }

    public function remove(): void
    {
        $this->user()->mailAccount()->delete();
        $this->reset();
        $this->mount();
    }

    public function render(): View
    {
        return view('livewire.settings.mail-account', ['account' => $this->user()->mailAccount, 'securities' => Account::securities(), 'gmail' => GmailConnectController::enabled(), 'outlook' => OutlookConnectController::enabled(), 'providers' => Account::PROVIDERS]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
