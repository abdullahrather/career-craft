<?php

declare(strict_types=1);

use App\Inbox\InboxMessage;
use App\Inbox\MailboxUnavailable;
use App\Inbox\MailReader;
use App\JobSources\FoundJob;
use App\JobSources\ListingStore;
use App\Mail\GmailTransport;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\JobListing;
use App\Models\MailAccount;
use App\Models\Mailbox;
use App\Models\Resume;
use App\Models\ResumeVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Live');

/** Records AI tokens the person has used, today unless another time is given. */
function useToday(User $user, int $tokens, ?DateTimeInterface $at = null): void
{
    AiUsage::query()->create(['user_id' => $user->id, 'feature' => 'test', 'provider' => 'ollama', 'model' => 'qwen2.5:7b', 'input_tokens' => $tokens, 'output_tokens' => 0, 'cost' => 0])
        ->forceFill(['created_at' => $at ?? now()])->save();
}

/** A fictional job as a source would find it. */
function foundJob(array $values = []): FoundJob
{
    return new FoundJob(...[
        'source' => 'arbeitnow',
        'externalId' => 'backend-developer-kranich-1',
        'url' => 'https://www.arbeitnow.com/jobs/backend-developer-kranich-1',
        'title' => 'Backend Developer PHP',
        'company' => 'Kranich Mobility GmbH',
        'location' => 'Hamburg',
        'workMode' => '',
        'description' => 'We build booking software for ferries.',
        ...$values,
    ]);
}

/** A one-page PDF with these lines of text. */
function pdfWith(array $lines): string
{
    $text = implode(' Tj T* ', array_map(fn (string $line): string => '('.addcslashes($line, '()\\').')', $lines));
    $content = "BT /F1 11 Tf 14 TL 72 760 Td {$text} Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $number => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/** A ZIP file with these files in it. */
function zipWith(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
}

/** A fictional person with a filled-in profile. */
function candidate(): User
{
    $user = User::factory()->create(['name' => 'Lena Brandt']);
    $profile = $user->careerProfile();
    $profile->update(['headline' => 'Product designer', 'summary' => 'I design tools that people in logistics use every day.', 'city' => 'Hamburg', 'country' => 'DE', 'phone' => '+49 40 7654321']);
    $profile->experiences()->create(['title' => 'Senior product designer', 'employer' => 'Hafenblick Studio', 'city' => 'Hamburg', 'starts_on' => '2021-03-01', 'achievements' => ['Raised task completion by 25%'], 'position' => 1]);
    $profile->educations()->create(['institution' => 'HAW Hamburg', 'degree' => 'Bachelor of Arts', 'field' => 'Communication design', 'starts_on' => '2014-10-01', 'ends_on' => '2018-07-01', 'grade' => '1.7', 'position' => 1]);
    $profile->skills()->create(['name' => 'Figma', 'level' => 4, 'position' => 1]);
    $profile->skills()->create(['name' => 'User research', 'level' => 3, 'position' => 2]);
    $profile->languages()->create(['name' => 'German', 'level' => 'native', 'position' => 1]);

    return $user;
}

function resumeFor(User $user, string $template = 'classic', string $language = 'en', array $settings = []): Resume
{
    return $user->resumes()->create([
        'name' => 'Design roles',
        'language' => $language,
        'template' => $template,
        'settings' => [...Resume::defaults($template), ...$settings],
    ]);
}

/** A fictional candidate: a PHP backend developer. */
function backendCandidate(): User
{
    $user = User::factory()->create();
    $profile = $user->careerProfile();
    $profile->update(['headline' => 'Backend Developer']);
    $profile->experiences()->create(['title' => 'Software Engineer', 'employer' => 'Nordlicht Logistik', 'city' => 'Hamburg', 'starts_on' => '2021-03-01']);
    foreach (['PHP', 'Laravel', 'PostgreSQL'] as $position => $skill) {
        $profile->skills()->create(['name' => $skill, 'position' => $position]);
    }

    return $user;
}

/** Saves fictional jobs, the first one newest. */
function saveJobs(array $jobs): void
{
    foreach (array_reverse($jobs) as $index => $values) {
        ListingStore::save([foundJob(['externalId' => "job-{$index}", 'company' => "Firma {$index}", ...$values])]);
        JobListing::query()->latest('id')->first()->update(['posted_at' => now()->subDays(count($jobs) - $index)]);
    }
}

/** A saved CV version with its PDF on the (fake) disk. */
function savedVersion(User $user, string $pdf = '%PDF-1.7 cv'): ResumeVersion
{
    $resume = resumeFor($user);
    Storage::disk('local')->put("resumes/{$resume->id}/1.pdf", $pdf);

    return $resume->versions()->create(['number' => 1, 'name' => 'Design roles', 'language' => 'en', 'template' => 'classic', 'pdf_path' => "resumes/{$resume->id}/1.pdf", 'docx_path' => "resumes/{$resume->id}/1.docx", 'text' => 'Lena Brandt', 'pages' => 1, 'checked' => 3, 'missing' => []]);
}

function readyLetter(User $user, array $values = []): CoverLetter
{
    return $user->coverLetters()->create(['language' => 'en', 'tone' => 'formal', 'length' => 'standard', 'recipient' => "Nordwind Logistik\nBremen", 'subject' => 'Application as Product Designer', 'body' => 'I design tools for depots.', 'checked' => true, 'status' => CoverLetter::READY, ...$values]);
}

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

/** What Google gives back after the person allows sending (or reading) email. */
function googleUser(array $scopes = ['openid', GmailTransport::SCOPE], ?string $refreshToken = 'refresh-1'): GoogleUser
{
    return (new GoogleUser)->setRaw([])->map(['id' => 'g-1', 'email' => 'lena.brandt@gmail.example', 'name' => 'Lena Brandt'])
        ->setToken('access-1')->setRefreshToken($refreshToken)->setExpiresIn(3600)->setApprovedScopes($scopes);
}

/** Google, as the controllers drive it, answering with this user. */
function fakeGoogle(GoogleUser $user): void
{
    $google = Mockery::mock(GoogleProvider::class);
    $google->shouldReceive('redirectUrl')->andReturnSelf();
    $google->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($google);
}

/**
 * A mailbox for the tests: what logging in answers, and the emails it holds. Each read is
 * recorded; an email's text can be read only when $readable says so.
 */
function fakeInbox(array $messages = [], ?string $login = null, ?string $failure = null, bool $loginFails = false): object
{
    $inbox = new class($messages, $login, $failure, $loginFails) implements MailReader
    {
        /** @var list<array{?int, CarbonInterface, int}> */
        public array $reads = [];

        public ?Mailbox $tested = null;

        public function __construct(public array $messages, public ?string $login, public ?string $failure, public bool $loginFails) {}

        public function test(Mailbox $mailbox): ?string
        {
            $this->tested = $mailbox;

            return $this->login;
        }

        public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
        {
            $this->reads[] = [$afterUid, $since, $limit];
            if ($this->failure !== null) {
                throw new MailboxUnavailable($this->failure, loginFailed: $this->loginFails);
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
