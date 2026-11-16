<?php

declare(strict_types=1);

use App\Ai\AiSettings;
use App\Jobs\AnalyseJobPost;
use App\Jobs\ImportJobListings;
use App\JobSources\Adzuna;
use App\JobSources\Arbeitnow;
use App\JobSources\Arbeitsagentur;
use App\JobSources\FoundJob;
use App\JobSources\ListingStore;
use App\JobSources\Normalise;
use App\Livewire\Discover\Index;
use App\Models\JobListing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

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

describe('Normalising', function (): void {
    it('takes the gender note out of titles', function (string $title): void {
        expect(Normalise::title($title))->toBe('Backend-Entwickler PHP');
    })->with(['Backend-Entwickler PHP (m/w/d)', 'Backend-Entwickler PHP (w/m/d)', 'Backend-Entwickler PHP (m/f/d)', 'Backend-Entwickler PHP (all genders)', 'Backend-Entwickler  PHP [m|w|d]', 'Backend-Entwickler PHP (gn*)']);

    it('turns HTML, escaped or not, into plain text with lines', function (): void {
        expect(Normalise::text('<p>Was du machst:</p><ul><li>APIs bauen</li><li>Tests &amp; Reviews</li></ul>'))->toBe("Was du machst:\n- APIs bauen\n- Tests & Reviews")
            ->and(Normalise::text('&lt;p&gt;Escaped&lt;/p&gt;&lt;p&gt;HTML&lt;/p&gt;'))->toBe("Escaped\nHTML");
    });

    it('keeps a link\'s words but not its address, so a search for "php" doesn\'t find "index.php"', function (): void {
        expect(Normalise::text('Wegewart:in gesucht. [JETZT BEWERBEN!](https://stellen.example.org/index.php?id=4) oder https://example.org/apply.php'))
            ->toBe('Wegewart:in gesucht. JETZT BEWERBEN! oder');
    });

    it('gives the same job on two sources the same fingerprint', function (): void {
        expect(Normalise::fingerprint('Backend Developer PHP (m/w/d)', 'Kranich Mobility GmbH', 'Hamburg, Germany'))
            ->toBe(Normalise::fingerprint('Backend Developer PHP', 'Kranich Mobility', 'Hamburg'))
            ->not->toBe(Normalise::fingerprint('Backend Developer PHP', 'Kranich Mobility', 'Bremen'));
    });
});

describe('The job sources', function (): void {
    it('read the Bundesagentur search, with each job\'s description from its details', function (): void {
        Http::fake([
            'rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v6/jobs*' => Http::response(['maxErgebnisse' => 2, 'ergebnisliste' => [
                ['referenznummer' => '10000-1-S', 'stellenangebotsTitel' => 'PHP-Entwickler (m/w/d)', 'firma' => 'Kranich Mobility GmbH', 'stellenlokationen' => [['adresse' => ['ort' => 'Hamburg', 'land' => 'DEUTSCHLAND']]], 'homeofficemoeglich' => true, 'veroeffentlichungszeitraum' => ['von' => '2026-11-02']],
                ['referenznummer' => '10000-2-S', 'hauptberuf' => 'Webentwickler/in', 'firma' => 'Möwe Digital'],
            ]]),
            'rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v4/jobdetails/'.base64_encode('10000-1-S') => Http::response(['stellenangebotsBeschreibung' => "Sie bauen APIs.\nMit Laravel."]),
            'rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v4/jobdetails/*' => Http::response([], 500),
        ]);

        [$first, $second] = (new Arbeitsagentur)->search('PHP', 'Hamburg');

        expect($first)->toEqual(new FoundJob('arbeitsagentur', '10000-1-S', 'https://www.arbeitsagentur.de/jobsuche/jobdetail/10000-1-S', 'PHP-Entwickler', 'Kranich Mobility GmbH', 'Hamburg', 'hybrid', "Sie bauen APIs.\nMit Laravel.", CarbonImmutable::parse('2026-11-02')))
            // Without details, the job stays, without a description.
            ->and($second->title)->toBe('Webentwickler/in')
            ->and($second->description)->toBe('');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'pc/v6/jobs') && $request['was'] === 'PHP' && $request['wo'] === 'Hamburg' && $request->header('X-API-Key') === ['jobboerse-jobsuche']);
    });

    it('read the Arbeitnow feed', function (): void {
        Http::fake(['www.arbeitnow.com/api/job-board-api*' => Http::response(['data' => [
            ['slug' => 'data-engineer-lumen-42', 'company_name' => 'Lumen Analytics', 'title' => 'Data Engineer (m/f/d)', 'description' => '<p>Pipelines in Python.</p>', 'remote' => true, 'url' => 'https://www.arbeitnow.com/jobs/data-engineer-lumen-42', 'location' => 'Berlin', 'created_at' => 1794800000],
            ['company_name' => 'No slug, skipped'],
        ]])]);

        $jobs = (new Arbeitnow)->latest();

        expect($jobs)->toHaveCount(1)
            ->and($jobs[0]->title)->toBe('Data Engineer')
            ->and($jobs[0]->workMode)->toBe('remote')
            ->and($jobs[0]->description)->toBe('Pipelines in Python.')
            ->and($jobs[0]->postedAt?->getTimestamp())->toBe(1794800000);
    });

    it('read Adzuna only with a key, without salaries it guessed itself', function (): void {
        expect(Adzuna::enabled())->toBeFalse();
        config(['services.adzuna.app_id' => 'id', 'services.adzuna.app_key' => 'key']);
        Http::fake(['api.adzuna.com/*' => Http::response(['results' => [
            ['id' => '4711', 'title' => 'PHP Developer', 'company' => ['display_name' => 'Möwe Digital'], 'location' => ['display_name' => 'Hamburg, Deutschland'], 'redirect_url' => 'https://www.adzuna.de/details/4711', 'created' => '2026-11-10T08:00:00Z', 'description' => 'Short snippet', 'salary_min' => 55000, 'salary_max' => 65000, 'salary_is_predicted' => '0'],
            ['id' => 4712, 'title' => 'Guessed salary', 'redirect_url' => 'https://www.adzuna.de/details/4712', 'salary_min' => 40000, 'salary_is_predicted' => '1'],
        ]])]);

        [$paid, $guessed] = (new Adzuna)->search('PHP', 'Hamburg');

        expect(Adzuna::enabled())->toBeTrue()
            ->and([$paid->salaryMin, $paid->salaryMax, $paid->salaryCurrency, $paid->salaryPeriod])->toBe([55000, 65000, 'EUR', 'year'])
            ->and($paid->company)->toBe('Möwe Digital')
            ->and($guessed->externalId)->toBe('4712')
            ->and($guessed->salaryMin)->toBeNull();
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/jobs/de/search/1') && $request['app_id'] === 'id' && $request['what'] === 'PHP');
    });
});

describe('Saving listings', function (): void {
    it('updates a job a source showed before, and merges one another source has', function (): void {
        expect(ListingStore::save([foundJob()]))->toBe(1);
        $this->travel(1)->day();

        // The same job from the same source: updated, not doubled.
        ListingStore::save([foundJob(['title' => 'Backend Developer PHP', 'workMode' => 'hybrid'])]);
        // From another source, written a little differently, with a longer description.
        ListingStore::save([foundJob(['source' => 'arbeitsagentur', 'externalId' => '10000-9-S', 'url' => 'https://www.arbeitsagentur.de/jobsuche/jobdetail/10000-9-S', 'title' => 'Backend Developer PHP (m/w/d)', 'company' => 'Kranich Mobility', 'description' => 'We build booking software for ferries, with Laravel and PostgreSQL.', 'salaryMin' => 60000, 'salaryCurrency' => 'EUR', 'salaryPeriod' => 'year'])]);
        // A shorter description doesn't replace a longer one.
        ListingStore::save([foundJob(['description' => 'Short.'])]);

        $listing = JobListing::query()->with('sources')->sole();
        expect($listing->sources->pluck('source')->sort()->values()->all())->toBe(['arbeitnow', 'arbeitsagentur'])
            ->and($listing->work_mode)->toBe('hybrid')
            ->and($listing->description)->toBe('We build booking software for ferries, with Laravel and PostgreSQL.')
            ->and($listing->salary_min)->toBe(60000)
            ->and($listing->last_seen_at->isToday())->toBeTrue();
    });

    it('lets a source shorten its own description', function (): void {
        ListingStore::save([foundJob(['description' => 'Apply at https://example.org/index.php?id=4 today.'])]);
        ListingStore::save([foundJob(['description' => 'Apply today.'])]);

        expect(JobListing::query()->value('description'))->toBe('Apply today.');
    });

    it('skips a job without a title or id', function (): void {
        expect(ListingStore::save([foundJob(['title' => '']), foundJob(['externalId' => ''])]))->toBe(0)
            ->and(JobListing::query()->count())->toBe(0);
    });

    it('keeps going when a source is down, and says it is done', function (): void {
        Http::fake(['www.arbeitnow.com/*' => Http::response('', 503)]);
        Cache::put(ImportJobListings::runningKey(7, 'arbeitnow'), true, 600);

        (new ImportJobListings('arbeitnow', '', '', 7))->handle();

        expect(Cache::has(ImportJobListings::runningKey(7, 'arbeitnow')))->toBeFalse()
            ->and(JobListing::query()->count())->toBe(0);
    });
});

describe('The Find jobs page', function (): void {
    it('is in the navigation, and fetches from each source once an hour per search', function (): void {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('discover'))->assertOk()->assertSee('Find jobs');

        $page = Livewire::actingAs($user)->test(Index::class)->set('what', ' PHP ')->set('where', 'Hamburg')->call('search')
            ->assertSet('what', 'PHP')
            ->assertSee('Fetching new jobs');
        // Without an Adzuna key, two sources.
        Queue::assertPushed(ImportJobListings::class, 2);
        Queue::assertPushed(ImportJobListings::class, fn (ImportJobListings $job): bool => $job->source === 'arbeitsagentur' && $job->what === 'PHP' && $job->where === 'Hamburg');

        $page->call('search');
        Livewire::actingAs($user)->test(Index::class)->set('what', 'Laravel')->call('search');
        // The same search again: nothing new. Another search: the Bundesagentur only, as Arbeitnow's feed is the same.
        Queue::assertPushed(ImportJobListings::class, 3);
    });

    it('shows recent jobs matching every word, in the place or remote', function (): void {
        ListingStore::save([
            foundJob(),
            foundJob(['externalId' => 'two', 'title' => 'Frontend Developer', 'company' => 'Möwe Digital', 'description' => 'Vue and PHP.', 'location' => 'Bremen']),
            foundJob(['externalId' => 'three', 'title' => 'PHP Backend Developer', 'company' => 'Lumen Analytics', 'location' => 'Berlin', 'workMode' => 'remote']),
            foundJob(['externalId' => 'four', 'title' => 'Old PHP job', 'company' => 'Gone GmbH']),
        ]);
        JobListing::query()->where('title', 'Old PHP job')->update(['last_seen_at' => now()->subDays(31)]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)->set('what', 'backend php')
            ->assertSee('Kranich Mobility GmbH')->assertSee('Lumen Analytics')->assertDontSee('Möwe Digital')->assertDontSee('Old PHP job')
            ->set('where', 'Hamburg')
            ->assertSee('Kranich Mobility GmbH')->assertSee('Lumen Analytics')
            ->set('remote', false)
            ->assertSee('Kranich Mobility GmbH')->assertDontSee('Lumen Analytics');
    });

    it('analyses a job like a pasted post, using its page when it has only a snippet', function (): void {
        Queue::fake();
        ListingStore::save([
            foundJob(['description' => str_repeat('We build booking software for ferries. ', 8)]),
            foundJob(['source' => 'adzuna', 'externalId' => '4711', 'url' => 'https://www.adzuna.de/details/4711', 'title' => 'PHP Developer', 'company' => 'Möwe Digital', 'description' => 'Short snippet']),
        ]);
        $user = User::factory()->create();
        [$full, $snippet] = JobListing::query()->orderBy('id')->get();

        Livewire::actingAs($user)->test(Index::class)->call('analyse', $full->id)->assertRedirect(route('jobs.show', ['id' => $user->jobPosts()->value('id')]));
        Livewire::actingAs($user)->test(Index::class)->call('analyse', $snippet->id);

        [$fromSnippet, $fromFull] = $user->jobPosts()->get();
        expect($fromFull->text)->toStartWith("Backend Developer PHP\nKranich Mobility GmbH · Hamburg\n\nWe build booking software")
            ->and($fromFull->url)->toBe('https://www.arbeitnow.com/jobs/backend-developer-kranich-1')
            ->and($fromSnippet->text)->toBeNull()
            ->and($fromSnippet->url)->toBe('https://www.adzuna.de/details/4711');
        Queue::assertPushed(AnalyseJobPost::class, 2);
    });

    it('analyses nothing once the day\'s AI allowance is used up', function (): void {
        Queue::fake();
        ListingStore::save([foundJob()]);
        (new AiSettings(dailyTokens: 100))->save();
        $user = User::factory()->create();
        useToday($user, 100);

        Livewire::actingAs($user)->test(Index::class)->call('analyse', JobListing::query()->value('id'))->assertHasErrors('analyse');

        expect($user->jobPosts()->count())->toBe(0);
    });
});
