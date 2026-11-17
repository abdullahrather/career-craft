<?php

declare(strict_types=1);

namespace App\Applications;

use App\Mail\ApplicationMail;
use App\Models\Application;
use App\Models\ApplicationEmail;
use App\Models\CoverLetter;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends applications from the candidate's own address, with the recorded CV and letter
 * attached. Each one is the candidate's own go-ahead, logged with what was sent.
 */
final readonly class EmailSender
{
    /** Sends a test email to the account's own address, or says why it couldn't. */
    public function test(MailAccount $account): ?string
    {
        return $this->deliver($account, $account->from_address, __('CareerCraft test email'), __('This test email shows that CareerCraft can send applications from your address.'), []);
    }

    /** Sends the application, or says why it couldn't. */
    public function send(Application $application, string $to, string $subject, string $body): ?string
    {
        $user = $application->user;
        $account = $user->mailAccount;
        if ($account === null || $account->verified_at === null) {
            return __('Set up your mail account in the settings first.');
        }
        $files = [];
        if ($application->cv_path !== null) {
            $files[(Str::slug((string) $application->cv_name) ?: 'cv').'.pdf'] = $application->cv_path;
        }
        if ($application->letter_path !== null) {
            $files[(Str::slug((string) $application->letter_name) ?: 'letter').'.pdf'] = $application->letter_path;
        }
        if ($files === []) {
            return __('Record the CV and letter you send first, under What you sent.');
        }
        if (self::sentToday($user) >= ApplicationEmail::DAILY) {
            return __('You can send :count application emails a day. Send the rest tomorrow.', ['count' => ApplicationEmail::DAILY]);
        }

        $error = $this->deliver($account, $to, $subject, $body, $files);
        if ($error !== null) {
            return $error;
        }
        $application->emails()->create(['to' => $to, 'subject' => $subject, 'body' => $body, 'attachments' => array_keys($files)]);
        $application->log('emailed', ['to' => $to]);
        if ($application->status === 'saved') {
            $application->moveTo('applied');
        }

        return null;
    }

    /**
     * The email to start from: to an address found in the job post, with a short note in the
     * letter's language, as the CV and letter go attached.
     *
     * @return array{to: string, subject: string, body: string}
     */
    public static function draft(Application $application): array
    {
        $language = ($application->cover_letter_id !== null ? CoverLetter::query()->whereKey($application->cover_letter_id)->value('language') : null)
            ?? $application->jobPost->language ?? app()->getLocale();
        $language = in_array($language, ['en', 'de'], true) ? $language : 'en';
        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', (string) $application->jobPost?->text, $found);
        $to = collect($found[0])->first(fn (string $address): bool => ! Str::contains(Str::lower($address), ['noreply', 'no-reply', 'donotreply'])) ?? '';

        return [
            'to' => rtrim($to, '.'),
            'subject' => $application->letter_name ?? __('Application as :title', ['title' => $application->title], $language),
            'body' => implode("\n\n", [
                __('Dear Sir or Madam,', [], $language),
                __('Please find attached my application for the position of :title, with my CV and cover letter.', ['title' => $application->title], $language),
                __('I look forward to hearing from you.', [], $language),
                __('Kind regards', [], $language)."\n".$application->user->name,
            ]),
        ];
    }

    /** How many application emails the person sent today. */
    public static function sentToday(User $user): int
    {
        return ApplicationEmail::query()->whereIn('application_id', $user->applications()->select('id'))->where('created_at', '>=', today())->count();
    }

    /** @param  array<string, string>  $files */
    private function deliver(MailAccount $account, string $to, string $subject, string $body, array $files): ?string
    {
        $mailer = $account->mailer();
        if (is_string($mailer)) {
            return $mailer;
        }
        try {
            $mailer->to($to)->send(new ApplicationMail($account->from_address, $account->from_name, $subject, $body, $files));
        } catch (TransportExceptionInterface $exception) {
            return __('The email could not be sent. Your mail server said: :reason', ['reason' => Str::limit($exception->getMessage(), 300)]);
        }

        return null;
    }
}
