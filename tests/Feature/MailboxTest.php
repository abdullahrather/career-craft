<?php

declare(strict_types=1);

use App\Inbox\InboxMessage;
use App\Inbox\MailboxCheck;
use App\Inbox\MailboxUnavailable;
use App\Inbox\MailReader;
use App\Inbox\ReplyMatcher;
use App\Jobs\CheckMailbox;
use App\Livewire\Applications\Show;
use App\Livewire\Settings\Mailbox as MailboxSettings;
use App\Models\Application;
use App\Models\MailAccount;
use App\Models\Mailbox;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * A mailbox for the tests: what logging in answers, and the emails it holds. Each read is
 * recorded; an email's text can be read only when $readable says so.
 */
function fakeInbox(array $messages = [], ?string $login = null, ?string $failure = null): object
{
    $inbox = new class($messages, $login, $failure) implements MailReader
    {
        /** @var list<array{?int, CarbonInterface, int}> */
        public array $reads = [];

        public ?Mailbox $tested = null;

        public function __construct(public array $messages, public ?string $login, public ?string $failure) {}

        public function test(Mailbox $mailbox): ?string
        {
            $this->tested = $mailbox;

            return $this->login;
        }

        public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
        {
            $this->reads[] = [$afterUid, $since, $limit];
            if ($this->failure !== null) {
                throw new MailboxUnavailable($this->failure);
            }

            return array_values(array_filter($this->messages, fn (InboxMessage $message): bool => $afterUid === null || $message->uid > $afterUid));
        }
    };
    app()->instance(MailReader::class, $inbox);

    return $inbox;
}

/** An email by its headers. Its text, when $body is null, must never be read. */
function inboxEmail(int $uid, string $from, string $subject, string $name = '', array $references = [], ?string $body = 'Vielen Dank für Ihre Bewerbung.', ?string $messageId = null): InboxMessage
{
    return new InboxMessage($uid, $messageId ?? "m{$uid}@mail.example", $from, $name, $subject, now()->subHour(), $references, function () use ($body, $uid): string {
        if ($body === null) {
            throw new LogicException("Email {$uid} matched nothing, so its text must not be read.");
        }

        return $body;
    });
}

function mailboxFor(User $user, array $values = []): Mailbox
{
    return $user->mailbox()->create(['host' => 'imap.mailbox.example', 'port' => 993, 'security' => 'ssl', 'username' => 'lena@mailbox.example', 'password' => 'app-password', ...$values]);
}

describe('Connecting a mailbox', function (): void {
    it('logs in first, and saves it with the password encrypted', function (): void {
        $inbox = fakeInbox();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(MailboxSettings::class)
            ->set(['boxHost' => 'IMAP.Mailbox.example', 'boxPort' => '993', 'boxSecurity' => 'ssl', 'boxUsername' => 'lena@mailbox.example', 'boxPassword' => 'app-password'])
            ->call('save')->assertHasNoErrors()->assertSee('Connected. CareerCraft checks it every 15 minutes')->assertSet('boxPassword', '')
            // The page now shows the mailbox as connected, with a way to check it.
            ->assertSee(['lena@mailbox.example, not checked yet.', 'Check now', 'Disconnect']);

        $mailbox = $user->mailbox()->sole();
        expect($inbox->tested?->only(['host', 'port', 'username', 'password']))->toBe(['host' => 'imap.mailbox.example', 'port' => 993, 'username' => 'lena@mailbox.example', 'password' => 'app-password'])
            ->and($mailbox->password)->toBe('app-password')
            ->and(DB::table('mailboxes')->value('password'))->not->toContain('app-password')
            ->and($mailbox->toArray())->not->toHaveKey('password');
    });

    it('saves nothing, and says why, when the login fails', function (): void {
        fakeInbox(login: 'The mail server didn\'t accept the user name or password. Many providers need an app password for this.');
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(MailboxSettings::class)
            ->set(['boxHost' => 'imap.mailbox.example', 'boxUsername' => 'lena@mailbox.example', 'boxPassword' => 'wrong'])
            ->call('save')->assertHasErrors('boxTest')->assertSee('need an app password');
        expect($user->mailbox()->exists())->toBeFalse();
    });

    it('keeps the saved password when left empty, and starts afresh only for another account', function (): void {
        $inbox = fakeInbox();
        $user = User::factory()->create();
        mailboxFor($user, ['last_uid' => 40, 'checked_at' => now(), 'error' => 'Login failed.']);

        Livewire::actingAs($user)->test(MailboxSettings::class)->call('save')->assertHasNoErrors();
        expect($inbox->tested?->password)->toBe('app-password')
            ->and($user->mailbox()->sole()->only(['last_uid', 'error']))->toBe(['last_uid' => 40, 'error' => null]);

        Livewire::actingAs($user)->test(MailboxSettings::class)->set('boxUsername', 'lena@other.example')->call('save')->assertHasNoErrors();
        expect($user->mailbox()->sole()->only(['username', 'last_uid', 'checked_at']))->toBe(['username' => 'lena@other.example', 'last_uid' => null, 'checked_at' => null]);
    });

    it('takes only public servers on IMAP ports outside development', function (): void {
        $this->app['env'] = 'production';
        MailAccount::$lookup = fn (string $host): array => [['type' => 'A', 'ip' => '93.184.216.34'], ['type' => 'AAAA', 'ipv6' => 'fd00::25']];
        expect(Mailbox::hostProblem('imap.mailbox.example', 993))->toContain('private network');
        MailAccount::$lookup = fn (string $host): array => [['type' => 'A', 'ip' => '93.184.216.34']];
        expect(Mailbox::hostProblem('imap.mailbox.example', 993))->toBeNull()
            ->and(Mailbox::hostProblem('imap.mailbox.example', 143))->toBeNull()
            ->and(Mailbox::hostProblem('imap.mailbox.example', 25))->toContain('993 or 143');
        MailAccount::$lookup = null;

        $inbox = fakeInbox();
        Livewire::actingAs(User::factory()->create())->test(MailboxSettings::class)
            ->set(['boxHost' => '10.0.0.5', 'boxUsername' => 'lena', 'boxPassword' => 'x'])->call('save')->assertHasErrors('boxHost');
        expect($inbox->tested)->toBeNull();
    });

    it('can be disconnected', function (): void {
        fakeInbox();
        $user = User::factory()->create();
        mailboxFor($user);

        Livewire::actingAs($user)->test(MailboxSettings::class)->call('remove');
        expect($user->mailbox()->exists())->toBeFalse();
    });
});

describe('Finding the application an email is about', function (): void {
    it('ties a reply to a sent application email by its thread', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer', 'company' => 'Elbe Werft']);
        $application->emails()->create(['to' => 'jobs@other.example', 'subject' => 'Bewerbung', 'body' => 'x', 'attachments' => [], 'message_id' => 'abc-123@mailbox.example']);

        [$found, $by] = (new ReplyMatcher($user))->match(inboxEmail(1, 'someone@unrelated.example', 'Re: Bewerbung', references: ['other@x.example', 'abc-123@mailbox.example']));
        expect($found->id)->toBe($application->id)->and($by)->toBe('thread');

        // The candidate's own follow-up in that thread isn't a reply.
        mailAccountFor($user);
        expect((new ReplyMatcher($user->refresh()))->match(inboxEmail(2, 'lena@mailbox.example', 'Re: Bewerbung', references: ['abc-123@mailbox.example'], body: null)))->toBeNull();
    });

    it('ties an email to the company by its sender\'s domain or name', function (string $from, string $name): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer', 'company' => 'Nordwind Logistik GmbH']);
        Application::track($user, ['title' => 'Engineer', 'company' => 'Hafenblick Studio']);

        [$found, $by] = (new ReplyMatcher($user))->match(inboxEmail(1, $from, 'Ihre Bewerbung', $name));
        expect($found->id)->toBe($application->id)->and($by)->toBe('sender');
    })->with([
        ['karriere@nordwind-logistik.de', ''],
        ['jobs@mail.nordwind.example', ''],
        ['talent@hr-mailer.example', 'Nordwind Logistik Recruiting'],
    ]);

    it('ties an applicant tracking system\'s email to the company it names', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);

        [$found, $by] = (new ReplyMatcher($user))->match(inboxEmail(1, 'no-reply@us.greenhouse-mail.io', 'Thank you for applying to Nordwind Logistik'));
        expect($found->id)->toBe($application->id)->and($by)->toBe('subject')
            ->and((new ReplyMatcher($user))->match(inboxEmail(2, 'no-reply@greenhouse.io', 'Thank you for applying to Hafenblick')))->toBeNull();
    });

    it('picks the job named in the subject when the company has several', function (): void {
        $user = User::factory()->create();
        $designer = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        Application::track($user, ['title' => 'Backend Engineer', 'company' => 'Nordwind Logistik'])->moveTo('applied');

        [$found] = (new ReplyMatcher($user))->match(inboxEmail(1, 'jobs@nordwind-logistik.de', 'Ihre Bewerbung als Product Designer'));
        expect($found->id)->toBe($designer->id);
    });

    it('leaves job alerts, its own emails and unrelated ones alone', function (string $from, string $subject, string $name): void {
        config(['mail.from.address' => 'hello@careercraft.test']);
        $user = User::factory()->create(['email' => 'lena@mailbox.example']);
        Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        Application::track($user, ['title' => 'Developer', 'company' => 'SAP']);

        expect((new ReplyMatcher($user))->match(inboxEmail(1, $from, $subject, $name, body: null)))->toBeNull();
    })->with([
        'a job alert' => ['jobs-noreply@linkedin.com', 'Nordwind Logistik is hiring a Product Designer', 'Nordwind Logistik via LinkedIn'],
        'CareerCraft\'s own' => ['hello@careercraft.test', 'New jobs for Nordwind Logistik', 'CareerCraft'],
        'the candidate\'s own' => ['lena@mailbox.example', 'Nordwind Logistik', 'Lena'],
        'another company' => ['news@shop.example', 'Your order', 'Shop'],
        'a short name inside a longer domain' => ['team@sapling.example', 'Hello', ''],
        'a short domain inside a longer name' => ['info@nord.example', 'Newsletter', ''],
    ]);
});

describe('Checking a mailbox', function (): void {
    it('keeps only the emails about an application, on its page and timeline, and reads only those', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        $mailbox = mailboxFor($user);
        $inbox = fakeInbox([
            inboxEmail(7, 'news@shop.example', 'Your order', body: null),
            inboxEmail(9, 'karriere@nordwind-logistik.de', 'Ihre Bewerbung als Product Designer', 'Nordwind Logistik', body: "Hallo Lena,\n\nwir laden Sie gern ein."),
        ]);

        expect(app(MailboxCheck::class)->run($mailbox))->toBe(1);

        $reply = $application->receivedEmails()->sole();
        expect($reply->only(['message_id', 'from_address', 'from_name', 'subject', 'body', 'matched_by']))->toBe(['message_id' => 'm9@mail.example', 'from_address' => 'karriere@nordwind-logistik.de', 'from_name' => 'Nordwind Logistik', 'subject' => 'Ihre Bewerbung als Product Designer', 'body' => "Hallo Lena,\n\nwir laden Sie gern ein.", 'matched_by' => 'sender'])
            ->and($application->events()->first()->text())->toBe('Email from Nordwind Logistik: “Ihre Bewerbung als Product Designer”')
            ->and($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => 9, 'error' => null])
            ->and($mailbox->checked_at)->not->toBeNull()
            // The first check reads 30 days back; the next only what's new.
            ->and($inbox->reads[0][0])->toBeNull()
            ->and($inbox->reads[0][1]->isSameDay(now()->subDays(30)))->toBeTrue();

        $inbox->messages[] = inboxEmail(9, 'karriere@nordwind-logistik.de', 'Ihre Bewerbung als Product Designer');
        $inbox->messages[] = inboxEmail(12, 'karriere@nordwind-logistik.de', 'Termin', messageId: 'm9@mail.example');
        expect(app(MailboxCheck::class)->run($mailbox))->toBe(0)
            ->and($inbox->reads[1][0])->toBe(9)
            ->and($application->receivedEmails()->count())->toBe(1)
            ->and($mailbox->refresh()->last_uid)->toBe(12);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->assertSee(['Replies', 'Ihre Bewerbung als Product Designer', 'wir laden Sie gern ein.']);
    });

    it('keeps an email with an overlong Message-ID once, however often it comes back', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer', 'company' => 'Nordwind Logistik']);
        $mailbox = mailboxFor($user);
        $longId = str_repeat('a', 300).'@nordwind-logistik.de';
        $inbox = fakeInbox([inboxEmail(1, 'jobs@nordwind-logistik.de', 'Eingangsbestätigung', messageId: $longId)]);

        app(MailboxCheck::class)->run($mailbox);
        // The same email again under a new UID, as after the server renumbers its emails.
        $inbox->messages[] = inboxEmail(2, 'jobs@nordwind-logistik.de', 'Eingangsbestätigung', messageId: $longId);
        app(MailboxCheck::class)->run($mailbox);

        expect($application->receivedEmails()->count())->toBe(1)
            ->and($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => 2, 'error' => null]);
    });

    it('keeps why a check failed, and carries on from where it got to once it works again', function (): void {
        $user = User::factory()->create();
        $mailbox = mailboxFor($user, ['last_uid' => 5]);
        $inbox = fakeInbox(failure: 'The mail server didn\'t accept the user name or password.');

        app(MailboxCheck::class)->run($mailbox);
        expect($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => 5, 'error' => 'The mail server didn\'t accept the user name or password.']);

        Livewire::actingAs($user)->test(MailboxSettings::class)->assertSee(['Not checking', 'didn\'t accept the user name']);

        $inbox->failure = null;
        Livewire::actingAs($user)->test(MailboxSettings::class)->call('check')->assertSee('No new replies');
        expect($mailbox->refresh()->error)->toBeNull()->and($inbox->reads[1][0])->toBe(5);
    });

    it('keeps the emails done before one whose text couldn\'t be read, and tries that one again', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer', 'company' => 'Nordwind Logistik']);
        $mailbox = mailboxFor($user);
        fakeInbox([
            inboxEmail(3, 'jobs@nordwind-logistik.de', 'Eingangsbestätigung'),
            new InboxMessage(4, 'm4@mail.example', 'jobs@nordwind-logistik.de', '', 'Einladung', now(), [], fn (): string => throw new MailboxUnavailable('Connection lost.')),
        ]);

        app(MailboxCheck::class)->run($mailbox);
        expect($application->receivedEmails()->pluck('subject')->all())->toBe(['Eingangsbestätigung'])
            ->and($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => 3, 'error' => 'Connection lost.']);
    });

    it('is checked by hand a few times an hour at most', function (): void {
        $user = User::factory()->create();
        mailboxFor($user);
        $inbox = fakeInbox();

        $page = Livewire::actingAs($user)->test(MailboxSettings::class);
        foreach (range(1, 4) as $ignored) {
            $page->call('check')->assertHasNoErrors();
        }
        $page->call('check')->assertHasErrors('boxTest')->assertSee('Checked a moment ago');
        expect($inbox->reads)->toHaveCount(4);
    });

    it('runs every 15 minutes for mailboxes whose last check worked', function (): void {
        Queue::fake();
        $working = mailboxFor(User::factory()->create());
        mailboxFor(User::factory()->create(), ['error' => 'Login failed.']);

        Artisan::call('careercraft:check-mailboxes');
        Queue::assertPushed(CheckMailbox::class, 1);
        Queue::assertPushed(CheckMailbox::class, fn (CheckMailbox $job): bool => $job->mailboxId === $working->id);
    });
});
