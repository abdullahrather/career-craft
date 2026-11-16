<?php

declare(strict_types=1);

use App\Jobs\ImportCompanyBoard;
use App\JobSources\Ashby;
use App\JobSources\CompanyBoards;
use App\JobSources\Greenhouse;
use App\JobSources\Lever;
use App\Livewire\Discover\Companies;
use App\Livewire\Discover\Index;
use App\Models\FollowedBoard;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** A fictional Greenhouse board with one job. */
function greenhouseBoard(): array
{
    return ['jobs' => [
        ['id' => 4001, 'title' => 'Platform Engineer (m/w/d)', 'absolute_url' => 'https://job-boards.greenhouse.io/kranichmobility/jobs/4001', 'location' => ['name' => 'Remote, Germany'], 'company_name' => 'Kranich Mobility', 'first_published' => '2026-11-10T09:00:00+01:00', 'content' => '&lt;p&gt;You run our &lt;strong&gt;Kubernetes&lt;/strong&gt; clusters.&lt;/p&gt;'],
    ]];
}

describe('Board links', function (): void {
    it('finds the board and company in a link to a board or one of its jobs', function (string $link, string $board, string $slug): void {
        expect(CompanyBoards::parse($link))->toBe(['board' => $board, 'slug' => $slug]);
    })->with([
        ['https://boards.greenhouse.io/kranichmobility', 'greenhouse', 'kranichmobility'],
        ['https://job-boards.greenhouse.io/kranichmobility/jobs/4001?gh_src=x', 'greenhouse', 'kranichmobility'],
        ['https://boards.greenhouse.io/embed/job_board?for=kranichmobility', 'greenhouse', 'kranichmobility'],
        ['jobs.lever.co/moewe-digital/2a55d6c9-c564-443a-be3e-a3851db5a93b', 'lever', 'moewe-digital'],
        ['https://jobs.eu.lever.co/moewe-digital', 'lever-eu', 'moewe-digital'],
        [' https://jobs.ashbyhq.com/LumenAnalytics/7458d4e9 ', 'ashby', 'LumenAnalytics'],
    ]);

    it('finds nothing in another link', function (string $link): void {
        expect(CompanyBoards::parse($link))->toBeNull();
    })->with(['https://www.linkedin.com/company/kranich', 'https://jobs.lever.co/', 'https://boards.greenhouse.io/embed/job_board', 'https://jobs.lever.co.example.org/kranich', 'not a link', 'https://jobs.ashbyhq.com/kranich%20mobility']);
});

describe('The board readers', function (): void {
    it('read a Greenhouse board, with its escaped HTML as text, and remote from the place', function (): void {
        Http::fake(['boards-api.greenhouse.io/v1/boards/kranichmobility/jobs*' => Http::response(greenhouseBoard())]);

        [$job] = (new Greenhouse)->board('kranichmobility');

        expect([$job->source, $job->externalId, $job->title, $job->company, $job->location, $job->workMode, $job->description])
            ->toBe(['greenhouse', '4001', 'Platform Engineer', 'Kranich Mobility', 'Remote, Germany', 'remote', 'You run our Kubernetes clusters.'])
            ->and($job->postedAt?->toIso8601String())->toBe('2026-11-10T09:00:00+01:00');
        Http::assertSent(fn (Request $request): bool => $request['content'] === 'true');
    });

    it('read a Lever board, with its lists, salary and work mode, from Lever\'s European address', function (): void {
        Http::fake(['api.eu.lever.co/v0/postings/moewe-digital*' => Http::response([
            ['id' => 'b1c2', 'text' => 'Backend Developer', 'hostedUrl' => 'https://jobs.eu.lever.co/moewe-digital/b1c2', 'categories' => ['location' => 'Bremen'], 'workplaceType' => 'hybrid', 'createdAt' => 1794800000000,
                'description' => '<div>We build shop software.</div>', 'lists' => [['text' => 'Your tasks', 'content' => '<li>APIs in PHP</li><li>Reviews</li>']], 'additional' => '<div>Apply in English or German.</div>',
                'salaryRange' => ['currency' => 'eur', 'interval' => 'per-year-salary', 'min' => 60000, 'max' => 72000]],
            ['id' => 'c3d4', 'text' => 'Working student', 'hostedUrl' => 'https://jobs.eu.lever.co/moewe-digital/c3d4', 'workplaceType' => 'on-site', 'salaryRange' => ['currency' => 'EUR', 'interval' => 'one-time', 'min' => 500]],
        ])]);

        [$backend, $student] = (new Lever)->board('moewe-digital', eu: true);

        expect($backend->description)->toBe("We build shop software.\nYour tasks\n- APIs in PHP\n- Reviews\n\nApply in English or German.")
            ->and([$backend->company, $backend->location, $backend->workMode])->toBe(['Moewe Digital', 'Bremen', 'hybrid'])
            ->and([$backend->salaryMin, $backend->salaryMax, $backend->salaryCurrency, $backend->salaryPeriod])->toBe([60000, 72000, 'EUR', 'year'])
            ->and($backend->postedAt?->getTimestamp())->toBe(1794800000)
            // A one-off payment isn't a salary.
            ->and([$student->workMode, $student->salaryMin])->toBe(['onsite', null]);
    });

    it('read an Ashby board, skipping unlisted jobs, with the salary among the pay parts', function (): void {
        Http::fake(['api.ashbyhq.com/posting-api/job-board/LumenAnalytics*' => Http::response(['jobs' => [
            ['id' => 'e5f6', 'title' => 'Data Engineer', 'jobUrl' => 'https://jobs.ashbyhq.com/LumenAnalytics/e5f6', 'location' => 'Berlin', 'workplaceType' => 'Remote', 'isListed' => true, 'publishedAt' => '2026-11-12T10:00:00.000+00:00', 'descriptionHtml' => '<p>Pipelines in Python.</p>',
                'compensation' => ['summaryComponents' => [
                    ['compensationType' => 'Bonus', 'interval' => '1 YEAR', 'currencyCode' => 'EUR', 'minValue' => 5000, 'maxValue' => 8000],
                    ['compensationType' => 'Salary', 'interval' => '1 YEAR', 'currencyCode' => 'EUR', 'minValue' => 65000, 'maxValue' => 80000],
                ]]],
            ['id' => 'g7h8', 'title' => 'Hidden role', 'jobUrl' => 'https://jobs.ashbyhq.com/LumenAnalytics/g7h8', 'isListed' => false],
        ]])]);

        $jobs = (new Ashby)->board('LumenAnalytics');

        expect($jobs)->toHaveCount(1)
            ->and([$jobs[0]->company, $jobs[0]->workMode, $jobs[0]->description])->toBe(['Lumen Analytics', 'remote', 'Pipelines in Python.'])
            ->and([$jobs[0]->salaryMin, $jobs[0]->salaryMax, $jobs[0]->salaryCurrency, $jobs[0]->salaryPeriod])->toBe([65000, 80000, 'EUR', 'year']);
        Http::assertSent(fn (Request $request): bool => $request['includeCompensation'] === 'true');
    });
});

describe('Following companies', function (): void {
    it('follows a board from a link, and shows its jobs straight away', function (): void {
        Http::fake(['boards-api.greenhouse.io/*' => Http::response(greenhouseBoard())]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Companies::class)
            ->set('link', 'https://job-boards.greenhouse.io/kranichmobility/jobs/4001')
            ->call('follow')
            ->assertHasNoErrors()
            ->assertSet('link', '')
            ->assertDispatched('jobs-imported')
            ->assertSee('Kranich Mobility')
            ->assertSee('Greenhouse')
            // The button's label starts with the word it shows, for voice control.
            ->assertSeeHtml('aria-label="Unfollow Kranich Mobility"');

        expect($user->followedBoards()->sole()->only(['board', 'slug', 'name']))->toBe(['board' => 'greenhouse', 'slug' => 'kranichmobility', 'name' => 'Kranich Mobility']);
        Livewire::actingAs($user)->test(Index::class)->assertSee('Platform Engineer');
    });

    it('says why a link can\'t be followed', function (): void {
        Http::fake([
            'api.lever.co/v0/postings/nobody*' => Http::response(['ok' => false, 'error' => 'Document not found'], 404),
            'api.lever.co/v0/postings/down*' => Http::response('', 503),
        ]);
        $user = User::factory()->create();
        $user->followedBoards()->create(['board' => 'ashby', 'slug' => 'LumenAnalytics', 'name' => 'Lumen Analytics']);
        $page = Livewire::actingAs($user)->test(Companies::class);

        $page->set('link', 'https://www.linkedin.com/company/kranich')->call('follow')->assertHasErrors('link')->assertSee('Greenhouse, Lever or Ashby');
        $page->set('link', 'https://jobs.ashbyhq.com/LumenAnalytics')->call('follow')->assertSee('You already follow this company.');
        $page->set('link', 'https://jobs.lever.co/nobody')->call('follow')->assertSee('There is no job board at this link.');
        $page->set('link', 'https://jobs.lever.co/down')->call('follow')->assertSee('The job board didn\'t answer.');

        expect($user->followedBoards()->count())->toBe(1);
    });

    it('unfollows only the person\'s own companies', function (): void {
        $user = User::factory()->create();
        $mine = $user->followedBoards()->create(['board' => 'lever', 'slug' => 'moewe-digital', 'name' => 'Moewe Digital']);
        $theirs = User::factory()->create()->followedBoards()->create(['board' => 'lever', 'slug' => 'moewe-digital', 'name' => 'Moewe Digital']);

        Livewire::actingAs($user)->test(Companies::class)->call('unfollow', $theirs->id)->call('unfollow', $mine->id);

        expect(FollowedBoard::query()->pluck('id')->all())->toBe([$theirs->id]);
    });
});

describe('The daily refresh', function (): void {
    it('fetches each followed board once, and deletes jobs no source has shown for a month', function (): void {
        Queue::fake();
        foreach (User::factory()->count(2)->create() as $user) {
            $user->followedBoards()->create(['board' => 'greenhouse', 'slug' => 'kranichmobility', 'name' => 'Kranich Mobility']);
        }
        $user->followedBoards()->create(['board' => 'lever-eu', 'slug' => 'moewe-digital', 'name' => 'Moewe Digital']);
        $old = JobListing::query()->create(['title' => 'Old job', 'description' => '', 'fingerprint' => sha1('old'), 'last_seen_at' => now()->subDays(31)]);
        $recent = JobListing::query()->create(['title' => 'Recent job', 'description' => '', 'fingerprint' => sha1('recent'), 'last_seen_at' => now()->subDays(29)]);

        $this->artisan('careercraft:refresh-jobs')->assertSuccessful();

        Queue::assertPushed(ImportCompanyBoard::class, 2);
        Queue::assertPushed(ImportCompanyBoard::class, fn (ImportCompanyBoard $job): bool => $job->board === 'lever-eu' && $job->slug === 'moewe-digital');
        expect(JobListing::query()->pluck('id')->all())->toBe([$recent->id])
            ->and(JobListing::query()->find($old->id))->toBeNull();
    });

    it('saves a board\'s jobs, and leaves the rest alone when a board is down', function (): void {
        Http::fake([
            'boards-api.greenhouse.io/*' => Http::response(greenhouseBoard()),
            'api.ashbyhq.com/*' => Http::response('', 500),
        ]);

        (new ImportCompanyBoard('greenhouse', 'kranichmobility'))->handle();
        (new ImportCompanyBoard('ashby', 'LumenAnalytics'))->handle();

        expect(JobListing::query()->pluck('title')->all())->toBe(['Platform Engineer']);
    });

    it('runs every morning', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'careercraft:refresh-jobs'));

        expect($event?->expression)->toBe('0 6 * * *');
    });
});
