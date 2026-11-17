<?php

declare(strict_types=1);

use App\Livewire\Settings\Extension;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A token as the settings page makes it, for the extension's requests. */
function extensionToken(User $user): string
{
    return $user->createToken('Browser extension', ['extension'])->plainTextToken;
}

describe('The extension API', function (): void {
    it('gives the person\'s details to fill a form with', function (): void {
        $user = candidate();
        $profile = $user->careerProfile();
        $profile->update(['email' => 'lena@hafenblick.example', 'salary_expectation' => 62000, 'notice_period' => '3 months', 'earliest_start' => '2027-03-01', 'work_permit' => 'EU citizen']);
        $profile->links()->create(['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/lena-brandt', 'position' => 1]);
        $profile->links()->create(['label' => 'Portfolio', 'url' => 'https://lena-brandt.example', 'position' => 2]);

        $this->withToken(extensionToken($user))->getJson(route('extension.profile'))->assertOk()->assertExactJson([
            'first_name' => 'Lena', 'last_name' => 'Brandt', 'email' => 'lena@hafenblick.example', 'phone' => '+49 40 7654321',
            'city' => 'Hamburg', 'country' => 'DE', 'headline' => 'Product designer',
            'linkedin' => 'https://www.linkedin.com/in/lena-brandt', 'github' => null, 'website' => 'https://lena-brandt.example',
            'salary_expectation' => 62000, 'notice_period' => '3 months', 'earliest_start' => '2027-03-01', 'work_permit' => 'EU citizen',
        ]);
    });

    it('finds the tracked application for the job page, its form page included, with its documents and answers', function (): void {
        Storage::fake('local');
        $user = User::factory()->create();
        $application = recordedApplication($user, ['url' => 'https://jobs.lever.co/nordwind/abc-123']);
        $user->applyQueueItems()->create(['application_id' => $application->id, 'title' => 'Product Designer', 'tone' => 'formal', 'answers_status' => 'ready', 'answers' => [['question' => 'why_fit', 'text' => 'I raised task completion by 25%.', 'flags' => [], 'checked' => true]]]);
        $token = extensionToken($user);

        $this->withToken($token)->getJson(route('extension.application', ['url' => 'https://www.jobs.lever.co/nordwind/abc-123/apply?lever-source=LinkedIn#form']))->assertOk()->assertExactJson([
            'id' => $application->id,
            'title' => 'Product Designer',
            'company' => 'Nordwind Logistik',
            'documents' => [
                'cv' => ['name' => 'design-roles-version-2.pdf', 'url' => route('extension.document', ['id' => $application->id, 'document' => 'cv'])],
                'letter' => ['name' => 'bewerbung-als-product-designer.pdf', 'url' => route('extension.document', ['id' => $application->id, 'document' => 'letter'])],
            ],
            'answers' => [['question' => 'why_fit', 'text' => 'I raised task completion by 25%.']],
        ]);
        $this->withToken($token)->get(route('extension.document', ['id' => $application->id, 'document' => 'cv']))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent('%PDF-1.7 cv');
    });

    it('says when the job isn\'t tracked', function (): void {
        $user = User::factory()->create();
        Application::track($user, ['title' => 'Designer', 'url' => 'https://jobs.lever.co/nordwind/abc-123']);

        $this->withToken(extensionToken($user))->getJson(route('extension.application', ['url' => 'https://jobs.lever.co/nordwind/xyz-999']))
            ->assertNotFound()->assertJson(['message' => 'This job isn\'t in your applications. Track it on CareerCraft first.']);
        $this->withToken(extensionToken($user))->getJson(route('extension.application', ['url' => 'javascript:alert(1)']))->assertNotFound();
    });

    it('serves each person only their own applications', function (): void {
        Storage::fake('local');
        $application = recordedApplication(User::factory()->create(), ['url' => 'https://jobs.lever.co/nordwind/abc-123']);
        $other = extensionToken(User::factory()->create());

        $this->withToken($other)->getJson(route('extension.application', ['url' => 'https://jobs.lever.co/nordwind/abc-123']))->assertNotFound();
        $this->withToken($other)->get(route('extension.document', ['id' => $application->id, 'document' => 'cv']))->assertNotFound();
    });

    it('needs a token made for the extension', function (): void {
        $user = User::factory()->create();

        $this->getJson(route('extension.profile'))->assertUnauthorized();
        $this->withToken('1|not-a-real-token')->getJson(route('extension.profile'))->assertUnauthorized();
        $this->withToken($user->createToken('Something else', ['other'])->plainTextToken)->getJson(route('extension.profile'))->assertForbidden();
        // Signed in on the website isn't enough: without an extension token it is refused.
        app('auth')->forgetGuards();
        $this->actingAs($user)->getJson(route('extension.profile'))->assertForbidden();
    });
});

describe('Extension tokens', function (): void {
    it('are made once, shown once, and can be revoked', function (): void {
        $user = User::factory()->create();

        $page = Livewire::actingAs($user)->test(Extension::class)->call('create')->assertSee('Copy it now');
        $token = $page->get('token');
        $this->withToken($token)->getJson(route('extension.profile'))->assertOk();
        expect($user->tokens()->sole()->abilities)->toBe(['extension']);

        Livewire::actingAs($user)->test(Extension::class)->assertDontSee($token)->assertSee('last used')
            ->call('revoke', $user->tokens()->sole()->id);
        expect($user->tokens()->count())->toBe(0);
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson(route('extension.profile'))->assertUnauthorized();
    });

    it('can only be revoked by their owner', function (): void {
        $owner = User::factory()->create();
        extensionToken($owner);

        Livewire::actingAs(User::factory()->create())->test(Extension::class)->call('revoke', $owner->tokens()->sole()->id);
        expect($owner->tokens()->count())->toBe(1);
    });
});

it('matches job page addresses in one form', function (string $url, ?string $normalised): void {
    expect(Application::normaliseUrl($url))->toBe($normalised);
})->with([
    ['https://jobs.lever.co/nordwind/abc-123/apply', 'jobs.lever.co/nordwind/abc-123'],
    ['https://WWW.Job-Boards.Greenhouse.io/gitlab/jobs/89/?gh_src=x#app', 'job-boards.greenhouse.io/gitlab/jobs/89'],
    ['http://example.com/', 'example.com'],
    ['ftp://example.com/job', null],
    ['not a url', null],
]);
