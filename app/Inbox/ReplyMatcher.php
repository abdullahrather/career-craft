<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Application;
use App\Models\ApplicationEmail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds the application an email in the candidate's mailbox is about, from its headers
 * alone: a reply to an application email CareerCraft sent, a sender whose domain or name is
 * the company's, or an applicant tracking system writing about the company or the job.
 * Everything else, including job alerts, is left alone.
 */
final class ReplyMatcher
{
    /** Applicant tracking systems, which send employers' emails from their own domains. */
    public const array TRACKING_SYSTEMS = ['greenhouse.io', 'greenhouse-mail.io', 'lever.co', 'hire.lever.co', 'ashbyhq.com', 'personio.de', 'personio.com', 'myworkday.com', 'workday.com', 'smartrecruiters.com', 'recruitee.com', 'workable.com', 'join.com', 'softgarden.de', 'softgarden.io', 'teamtailor.com', 'bamboohr.com'];

    /** Job boards and networks, whose emails are alerts and newsletters, not replies. */
    public const array JOB_BOARDS = ['linkedin.com', 'indeed.com', 'stepstone.de', 'xing.com', 'glassdoor.com', 'arbeitsagentur.de', 'monster.de', 'kununu.com'];

    /** Endings of company names that its domain usually leaves out. */
    private const string LEGAL_FORMS = '/\b(gmbh|mbh|ag|se|kg|kgaa|ohg|ug|e\.?\s?v|co|inc|ltd|llc|plc|corp|corporation|company|group|holding)\b\.?/u';

    /** @var Collection<int, Application>|null */
    private ?Collection $applications = null;

    /** @var array<string, int>|null */
    private ?array $sent = null;

    public function __construct(private User $user) {}

    /**
     * The application the email is about, and what tied it ("thread", "sender" or "subject"),
     * or null when it isn't about any.
     *
     * @return array{0: Application, 1: string}|null
     */
    public function match(InboxMessage $message): ?array
    {
        $domain = Str::after($message->fromAddress, '@');
        if ($domain === '' || $this->ownAddress($message->fromAddress) || $this->within($domain, self::JOB_BOARDS)) {
            return null;
        }

        foreach ($message->references as $reference) {
            $id = $this->sent()[$reference] ?? null;
            if ($id !== null && ($application = $this->applications()->firstWhere('id', $id)) !== null) {
                return [$application, 'thread'];
            }
        }

        $tracking = $this->within($domain, self::TRACKING_SYSTEMS);
        $found = [];
        foreach ($this->applications() as $application) {
            $company = $this->words($application->company);
            if ($company === '') {
                continue;
            }
            if (! $tracking && ($this->domainIsCompany($domain, $company) || $this->contains($message->fromName, $company))) {
                $found[] = [$application, 'sender'];
            } elseif ($tracking && ($this->contains($message->fromName, $company) || $this->contains($message->subject, $company))) {
                $found[] = [$application, 'subject'];
            }
        }
        if ($found === []) {
            return null;
        }

        // Several jobs at one company: the one named in the subject, else the latest applied for.
        usort($found, fn (array $a, array $b): int => [$this->contains($message->subject, $this->words($b[0]->title)), $b[0]->applied_on->timestamp ?? 0, $b[0]->id]
            <=> [$this->contains($message->subject, $this->words($a[0]->title)), $a[0]->applied_on->timestamp ?? 0, $a[0]->id]);

        return $found[0];
    }

    /** Whether the domain's own name is the company's: nordwind-logistik.de for "Nordwind Logistik GmbH". */
    private function domainIsCompany(string $domain, string $company): bool
    {
        $labels = explode('.', $domain);
        array_pop($labels);
        // co.uk, com.au and the like: the name is the label before.
        if (count($labels) > 1 && in_array(end($labels), ['co', 'com', 'org', 'net', 'ac'], true)) {
            array_pop($labels);
        }
        $name = str_replace('-', '', (string) end($labels));
        $key = str_replace(' ', '', $company);

        return $name !== '' && ($name === $key || (strlen($name) >= 5 && str_starts_with($key, $name)) || (strlen($key) >= 5 && str_starts_with($name, $key)));
    }

    /** Whether the text has the words, as whole words, ignoring case and punctuation. */
    private function contains(string $text, string $words): bool
    {
        return $words !== '' && preg_match('/(^|\s)'.preg_quote($words, '/').'(\s|$)/u', $this->words($text, false)) === 1;
    }

    /** Lower case, letters and digits only, and for a company name without its legal form. */
    private function words(string $text, bool $company = true): string
    {
        $text = Str::lower(Str::ascii($text));
        if ($company) {
            $text = (string) preg_replace(self::LEGAL_FORMS, ' ', $text);
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    /** @param  list<string>  $domains */
    private function within(string $domain, array $domains): bool
    {
        foreach ($domains as $known) {
            if ($domain === $known || str_ends_with($domain, '.'.$known)) {
                return true;
            }
        }

        return false;
    }

    /** The candidate's own addresses, and CareerCraft's, whose emails are never replies. */
    private function ownAddress(string $address): bool
    {
        $own = [$this->user->email, $this->user->mailAccount?->from_address, $this->user->mailbox?->username, config()->string('mail.from.address', '')];

        return in_array($address, array_map(fn (?string $value): string => Str::lower((string) $value), array_filter($own)), true);
    }

    /** @return Collection<int, Application> */
    private function applications(): Collection
    {
        return $this->applications ??= $this->user->applications()->get();
    }

    /** @return array<string, int> Each sent application email's Message-ID, with its application. */
    private function sent(): array
    {
        if ($this->sent === null) {
            $this->sent = [];
            $emails = ApplicationEmail::query()->whereIn('application_id', $this->user->applications()->select('id'))->whereNotNull('message_id')->get(['application_id', 'message_id']);
            foreach ($emails as $email) {
                $this->sent[(string) $email->message_id] = $email->application_id;
            }
        }

        return $this->sent;
    }
}
