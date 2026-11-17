<?php

declare(strict_types=1);

use App\Livewire\Applications\Email;
use App\Livewire\Settings\MailAccount as MailAccountSettings;
use App\Models\Application;
use App\Models\ApplicationEmail;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\RawMessage;

/** Mail servers the app builds go to memory instead; each one's settings are kept. */
function fakeMailServer(bool $down = false): object
{
    $server = new class
    {
        public ArrayTransport $transport;

        public array $settings = [];

        /** @return list<SymfonyEmail> */
        public function sent(): array
        {
            return $this->transport->messages()->map(fn (SentMessage $sent) => $sent->getOriginalMessage())->all();
        }
    };
    $server->transport = new ArrayTransport;
    Mail::extend('smtp', function (array $settings) use ($server, $down): TransportInterface {
        $server->settings[] = $settings;

        return $down ? new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('535 5.7.8 Authentication failed');
            }

            public function __toString(): string
            {
                return 'down';
            }
        } : $server->transport;
    });

    return $server;
}

function mailAccountFor(User $user, array $values = []): MailAccount
{
    return $user->mailAccount()->create(['host' => 'smtp.mailbox.example', 'port' => 587, 'security' => 'starttls', 'username' => 'lena@mailbox.example', 'password' => 'app-password', 'from_address' => 'lena@mailbox.example', 'from_name' => 'Lena Brandt', 'verified_at' => now(), ...$values]);
}

/** An application with its CV and letter recorded, on the fake disk. */
function recordedApplication(User $user, array $values = []): Application
{
    $application = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik', ...$values]);
    $directory = Application::directory($application->id);
    Storage::disk('local')->put("{$directory}/cv.pdf", '%PDF-1.7 cv');
    Storage::disk('local')->put("{$directory}/letter.pdf", '%PDF-1.7 letter');
    $application->update(['cv_name' => 'Design roles, version 2', 'cv_path' => "{$directory}/cv.pdf", 'letter_name' => 'Bewerbung als Product Designer', 'letter_path' => "{$directory}/letter.pdf"]);

    return $application;
}

describe('The mail account', function (): void {
    it('is saved with an encrypted password, and tested by an email to the person\'s own address', function (): void {
        $server = fakeMailServer();
        $user = User::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@mailbox.example']);

        Livewire::actingAs($user)->test(MailAccountSettings::class)
            ->assertSet('mailFrom', 'lena@mailbox.example')
            ->set(['mailHost' => 'smtp.mailbox.example', 'mailPort' => '465', 'mailSecurity' => 'ssl', 'mailPassword' => 'app-password'])
            ->call('save')->assertHasNoErrors()
            ->assertSee('A test email went to lena@mailbox.example.')
            ->assertSet('mailPassword', '')
            ->assertDontSeeHtml('app-password');

        $account = $user->mailAccount()->sole();
        expect($account->verified_at)->not->toBeNull()
            ->and($account->password)->toBe('app-password')
            ->and(DB::table('mail_accounts')->value('password'))->not->toContain('app-password')
            ->and($server->settings[0])->toMatchArray(['host' => 'smtp.mailbox.example', 'port' => 465, 'scheme' => 'smtps', 'username' => 'lena@mailbox.example', 'password' => 'app-password'])
            ->and($server->sent()[0]->getTo()[0]->getAddress())->toBe('lena@mailbox.example')
            ->and($server->sent()[0]->getSubject())->toBe('CareerCraft test email');
    });

    it('keeps the saved password when left empty, and needs STARTTLS when chosen', function (): void {
        $server = fakeMailServer();
        $user = User::factory()->create();
        mailAccountFor($user, ['verified_at' => null]);

        Livewire::actingAs($user)->test(MailAccountSettings::class)->set('mailSecurity', 'starttls')->call('save')->assertHasNoErrors();

        expect($user->mailAccount()->sole()->password)->toBe('app-password')
            ->and($server->settings[0])->toMatchArray(['scheme' => 'smtp', 'require_tls' => true, 'auto_tls' => true]);
    });

    it('stays untested, and says why, when the test email fails', function (): void {
        fakeMailServer(down: true);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(MailAccountSettings::class)
            ->set(['mailHost' => 'smtp.mailbox.example', 'mailPassword' => 'wrong'])->call('save')
            ->assertHasErrors('test')->assertSee('535 5.7.8 Authentication failed');

        expect($user->mailAccount()->sole()->verified_at)->toBeNull();
    });

    it('can be removed', function (): void {
        $user = User::factory()->create();
        mailAccountFor($user);

        Livewire::actingAs($user)->test(MailAccountSettings::class)->call('remove');
        expect(MailAccount::query()->count())->toBe(0);
    });

    it('takes only public mail servers on mail ports outside development', function (): void {
        $this->app['env'] = 'production';

        expect(MailAccount::hostProblem('192.168.1.10', 587))->toContain('private network')
            ->and(MailAccount::hostProblem('127.0.0.1', 587))->toContain('private network')
            ->and(MailAccount::hostProblem('1.1.1.1', 1025))->toContain('465, 587, 25 or 2525')
            ->and(MailAccount::hostProblem('1.1.1.1', 587))->toBeNull()
            ->and(MailAccount::securities())->toBe(['ssl', 'starttls']);

        Livewire::actingAs(User::factory()->create())->test(MailAccountSettings::class)
            ->set(['mailHost' => 'mailpit', 'mailPort' => '1025', 'mailSecurity' => 'none', 'mailPassword' => 'x'])->call('save')
            ->assertHasErrors('mailSecurity');
    });
});

describe('Sending an application', function (): void {
    it('sends it from the person\'s address with the CV and letter attached, and logs it', function (): void {
        Storage::fake('local');
        $server = fakeMailServer();
        $user = User::factory()->create(['name' => 'Lena Brandt']);
        mailAccountFor($user);
        $application = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'Bewerbung als Product Designer', 'emailBody' => "Sehr geehrte Damen und Herren,\n\nanbei meine Bewerbung."])
            ->call('send')->assertHasNoErrors()
            ->assertSee(['Sent to jobs@nordwind.example.', 'design-roles-version-2.pdf']);

        $email = $server->sent()[0];
        expect($email->getTo()[0]->getAddress())->toBe('jobs@nordwind.example')
            ->and([$email->getFrom()[0]->getName(), $email->getFrom()[0]->getAddress()])->toBe(['Lena Brandt', 'lena@mailbox.example'])
            ->and($email->getSubject())->toBe('Bewerbung als Product Designer')
            ->and(trim((string) $email->getTextBody()))->toBe("Sehr geehrte Damen und Herren,\n\nanbei meine Bewerbung.")
            ->and(array_map(fn ($part) => $part->getFilename(), $email->getAttachments()))->toBe(['design-roles-version-2.pdf', 'bewerbung-als-product-designer.pdf'])
            ->and($email->getAttachments()[0]->getBody())->toBe('%PDF-1.7 cv');

        $application->refresh();
        expect($application->emails()->sole()->only(['to', 'subject', 'attachments']))->toBe(['to' => 'jobs@nordwind.example', 'subject' => 'Bewerbung als Product Designer', 'attachments' => ['design-roles-version-2.pdf', 'bewerbung-als-product-designer.pdf']])
            ->and($application->status)->toBe('applied')
            ->and($application->events()->first()->text())->toBe('Moved from Saved to Applied')
            ->and($application->events()->skip(1)->first()->text())->toBe('Sent by email to jobs@nordwind.example');
    });

    it('starts from an address in the job post and a short note in the letter\'s language', function (): void {
        $user = User::factory()->create(['name' => 'Lena Brandt']);
        $post = $user->jobPosts()->create(['status' => 'ready', 'text' => 'Fragen an noreply@nordwind.example. Bewerbungen an karriere@nordwind.example.', 'title' => 'Product Designer', 'language' => 'de']);
        $application = Application::track($user, ['title' => 'Product Designer', 'job_post_id' => $post->id]);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->assertSet('emailTo', 'karriere@nordwind.example')
            ->assertSet('emailSubject', 'Bewerbung als Product Designer')
            ->assertSet('emailBody', "Sehr geehrte Damen und Herren,\n\nanbei sende ich Ihnen meine Bewerbung als Product Designer mit Lebenslauf und Anschreiben.\n\nIch freue mich darauf, von Ihnen zu hören.\n\nMit freundlichen Grüßen\nLena Brandt");
    });

    it('needs a tested mail account and recorded documents', function (): void {
        Storage::fake('local');
        fakeMailServer();
        $user = User::factory()->create();
        $bare = Application::track($user, ['title' => 'Product Designer']);
        $recorded = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $recorded->id])->assertSee('Set up your mail account first');
        mailAccountFor($user, ['verified_at' => null]);
        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $recorded->id])
            ->set(['emailTo' => 'jobs@nordwind.example'])->call('send')->assertHasErrors('send');
        $user->mailAccount->update(['verified_at' => now()]);
        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $bare->id])
            ->assertSee('Record the CV and letter you send first')
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'Hi', 'emailBody' => 'Hi'])->call('send')->assertHasErrors('send');

        expect(ApplicationEmail::query()->count())->toBe(0);
    });

    it('sends 20 a day, and logs nothing when the server refuses', function (): void {
        Storage::fake('local');
        fakeMailServer();
        $user = User::factory()->create();
        mailAccountFor($user);
        $application = recordedApplication($user);
        foreach (range(1, ApplicationEmail::DAILY) as $i) {
            $application->emails()->create(['to' => "hr{$i}@example.com", 'subject' => 'S', 'body' => 'B', 'attachments' => []]);
        }
        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'S', 'emailBody' => 'B'])->call('send')
            ->assertSee('You can send 20 application emails a day.');

        $this->travel(1)->day();
        fakeMailServer(down: true);
        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'S', 'emailBody' => 'B'])->call('send')
            ->assertSee('Your mail server said: 535 5.7.8 Authentication failed');
        expect($application->emails()->count())->toBe(ApplicationEmail::DAILY);
    });

    it('is the owner\'s alone', function (): void {
        $application = recordedApplication(User::factory()->create());

        Livewire::actingAs(User::factory()->create())->test(Email::class, ['applicationId' => $application->id])->assertNotFound();
    });
});
