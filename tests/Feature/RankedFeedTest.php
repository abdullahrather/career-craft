<?php

declare(strict_types=1);

use App\JobSearch\ListingRanker;
use App\JobSources\ListingStore;
use App\Livewire\Discover\Index;
use App\Models\JobListing;
use App\Models\User;
use Livewire\Livewire;

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

/** Saves fictional jobs, the first one newest, and gives back their titles in the order the page shows them. */
function feedTitles(User $user, array $set = []): array
{
    $page = Livewire::actingAs($user)->test(Index::class);
    foreach ($set as $property => $value) {
        $page->set($property, $value);
    }

    return array_values(array_map(fn (JobListing $listing): string => $listing->title, $page->viewData('listings')->items()));
}

function saveJobs(array $jobs): void
{
    foreach (array_reverse($jobs) as $index => $values) {
        ListingStore::save([foundJob(['externalId' => "job-{$index}", 'company' => "Firma {$index}", ...$values])]);
        JobListing::query()->latest('id')->first()->update(['posted_at' => now()->subDays(count($jobs) - $index)]);
    }
}

describe('The ranked feed', function (): void {
    it('puts the jobs that fit the profile first: skills in the title, then in the text, then title words', function (): void {
        saveJobs([
            ['title' => 'Office Manager', 'description' => 'Organise our office.'],
            ['title' => 'Data Analyst', 'description' => 'Reports with Postgres and Python.'],
            ['title' => 'Backend Developer', 'description' => 'Java and Spring.'],
            ['title' => 'PHP Developer', 'description' => 'Laravel and PostgreSQL every day.'],
        ]);

        expect(feedTitles(backendCandidate()))->toBe(['PHP Developer', 'Backend Developer', 'Data Analyst', 'Office Manager'])
            ->and(feedTitles(backendCandidate(), ['sort' => 'newest']))->toBe(['Office Manager', 'Data Analyst', 'Backend Developer', 'PHP Developer']);
    });

    it('shows which of the person\'s skills a job asks for', function (): void {
        saveJobs([['title' => 'PHP Developer', 'description' => 'Laravel and Postgres.']]);

        Livewire::actingAs(backendCandidate())->test(Index::class)->assertSee('Your skills: PHP, Laravel, PostgreSQL');
    });

    it('comes newest first, with a hint, for a profile with nothing to rank by', function (): void {
        saveJobs([['title' => 'Office Manager', 'description' => ''], ['title' => 'PHP Developer', 'description' => '']]);
        $user = User::factory()->create();

        expect(feedTitles($user))->toBe(['Office Manager', 'PHP Developer']);
        Livewire::actingAs($user)->test(Index::class)->assertSee('Add skills or job titles to your career profile');
    });

    it('pages through the ranked jobs', function (): void {
        saveJobs(array_map(fn (int $i): array => ['title' => "Job {$i}", 'description' => $i % 2 === 0 ? 'PHP' : ''], range(1, 25)));

        $page = Livewire::actingAs(backendCandidate())->test(Index::class);
        $first = $page->viewData('listings');
        $second = $page->call('nextPage')->viewData('listings');

        expect($first->items())->toHaveCount(20)
            ->and($first->hasMorePages())->toBeTrue()
            ->and($second->items())->toHaveCount(5)
            ->and($second->hasMorePages())->toBeFalse()
            ->and(collect([...$first->items(), ...$second->items()])->pluck('id')->unique())->toHaveCount(25);
    });

    it('scores a job again when it changes', function (): void {
        saveJobs([['title' => 'Designer', 'description' => 'Java.'], ['title' => 'Engineer', 'description' => 'Ruby.']]);
        $user = backendCandidate();
        expect(feedTitles($user))->toBe(['Engineer', 'Designer']);

        $this->travel(1)->minute();
        ListingStore::save([foundJob(['externalId' => 'job-1', 'company' => 'Firma 1', 'title' => 'Designer', 'description' => 'Laravel, PHP and Postgres, not just Java.'])]);

        expect(feedTitles($user))->toBe(['Designer', 'Engineer']);
    });

    it('filters by work mode and salary', function (): void {
        saveJobs([
            ['title' => 'Remote PHP', 'workMode' => 'remote'],
            ['title' => 'Paid PHP', 'workMode' => 'onsite', 'salaryMin' => 60000, 'salaryCurrency' => 'EUR', 'salaryPeriod' => 'year'],
            ['title' => 'Plain PHP'],
        ]);
        $user = backendCandidate();

        expect(feedTitles($user, ['mode' => 'remote']))->toBe(['Remote PHP'])
            ->and(feedTitles($user, ['salary' => true]))->toBe(['Paid PHP']);
    });
});

describe('Not interested', function (): void {
    it('hides a job, ranks jobs with similar titles lower, and can be undone', function (): void {
        saveJobs([
            ['title' => 'Frontend Developer', 'description' => 'PHP templates.'],
            ['title' => 'Sales Developer', 'description' => 'PHP and Laravel.'],
            ['title' => 'Sales Manager', 'description' => 'PHP knowledge helps.'],
        ]);
        $user = backendCandidate();
        expect(feedTitles($user))->toBe(['Sales Developer', 'Frontend Developer', 'Sales Manager']);

        $manager = JobListing::query()->where('title', 'Sales Manager')->value('id');
        $page = Livewire::actingAs($user)->test(Index::class)->call('dismiss', $manager)
            ->assertSee('Hidden: Sales Manager.');

        // "Sales" now counts against a job; "Developer" doesn't, as the profile uses it.
        expect(array_map(fn (JobListing $listing): string => $listing->title, $page->viewData('listings')->items()))->toBe(['Frontend Developer', 'Sales Developer']);

        $page->call('undo');
        expect(feedTitles($user))->toBe(['Sales Developer', 'Frontend Developer', 'Sales Manager'])
            ->and($user->dismissedListings()->count())->toBe(0);
    });

    it('keeps tuning the ranking after the hidden job is gone', function (): void {
        saveJobs([['title' => 'Office Clerk'], ['title' => 'Sales Developer'], ['title' => 'Backend Developer'], ['title' => 'Sales Lead']]);
        $user = backendCandidate();
        Livewire::actingAs($user)->test(Index::class)->call('dismiss', JobListing::query()->where('title', 'Sales Lead')->value('id'));

        JobListing::query()->where('title', 'Sales Lead')->delete();

        expect($user->dismissedListings()->sole()->title)->toBe('Sales Lead')
            ->and(feedTitles($user))->toBe(['Backend Developer', 'Office Clerk', 'Sales Developer']);
    });

    it('hides jobs only from the person who marked them', function (): void {
        saveJobs([['title' => 'PHP Developer']]);
        Livewire::actingAs(backendCandidate())->test(Index::class)->call('dismiss', JobListing::query()->value('id'));

        expect(feedTitles(backendCandidate()))->toBe(['PHP Developer']);
    });
});

it('needs something in the profile to rank by', function (): void {
    expect((new ListingRanker(1, null, []))->canRank())->toBeFalse();
});
