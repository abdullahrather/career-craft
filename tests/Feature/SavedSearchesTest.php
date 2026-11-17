<?php

declare(strict_types=1);

use App\Jobs\ImportJobListings;
use App\JobSearch\ListingFilters;
use App\Livewire\Discover\Index;
use App\Models\JobListing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\JobAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** Saves a search for the person, checked now: only jobs found after it are new. */
function savedSearch(User $user, array $values = []): SavedSearch
{
    return $user->savedSearches()->create(['frequency' => 'daily', 'checked_at' => now(), ...$values]);
}

/** The titles in the alerts sent to the person. */
function alertedTitles(User $user): array
{
    return Notification::sent($user, JobAlert::class)->map(fn (JobAlert $alert): array => array_column($alert->jobs, 'title'))->all();
}

describe('Saved searches', function (): void {
    it('saves the search on the page, and opening it runs it again', function (): void {
        Queue::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)
            ->set(['what' => ' PHP ', 'where' => 'Hamburg', 'mode' => 'remote', 'salary' => true, 'frequency' => 'weekly'])
            ->call('saveSearch')
            ->assertHasNoErrors()
            ->assertSee('Search saved.')
            ->assertSee('PHP · Hamburg · Remote · with a salary');

        $saved = $user->savedSearches()->sole();
        expect($saved->only(['what', 'where', 'remote', 'mode', 'salary', 'company', 'frequency']))->toBe(['what' => 'PHP', 'where' => 'Hamburg', 'remote' => true, 'mode' => 'remote', 'salary' => true, 'company' => '', 'frequency' => 'weekly'])
            ->and($saved->checked_at->isSameMinute(now()))->toBeTrue();

        Livewire::actingAs($user)->test(Index::class)->call('openSearch', $saved->id)
            ->assertSet('what', 'PHP')->assertSet('where', 'Hamburg')->assertSet('mode', 'remote')->assertSet('salary', true);
        Queue::assertPushed(ImportJobListings::class, fn (ImportJobListings $job): bool => $job->source === 'arbeitsagentur' && $job->what === 'PHP' && $job->where === 'Hamburg');
    });

    it('changes only how often it emails when the same search is saved again', function (): void {
        $user = User::factory()->create();
        $page = Livewire::actingAs($user)->test(Index::class)->set('what', 'PHP')->call('saveSearch');
        $page->set('frequency', 'weekly')->call('saveSearch');

        expect($user->savedSearches()->sole()->frequency)->toBe('weekly');
    });

    it('saves up to 20 searches', function (): void {
        $user = User::factory()->create();
        foreach (range(1, SavedSearch::MAX) as $i) {
            savedSearch($user, ['what' => "Job {$i}"]);
        }

        Livewire::actingAs($user)->test(Index::class)->set('what', 'One more')->call('saveSearch')
            ->assertHasErrors('frequency')
            ->assertSee('You can save up to 20 searches.');
        expect($user->savedSearches()->count())->toBe(SavedSearch::MAX);
    });

    it('rejects a search the page could not have made', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)->set(['mode' => 'on the moon', 'frequency' => 'hourly'])->call('saveSearch')
            ->assertHasErrors(['mode', 'frequency']);
        expect($user->savedSearches()->count())->toBe(0);
    });

    it('changes and deletes the person\'s own saved searches only', function (): void {
        $user = User::factory()->create();
        $mine = savedSearch($user, ['what' => 'PHP']);
        $theirs = savedSearch(User::factory()->create(), ['what' => 'Go']);
        $page = fn () => Livewire::actingAs($user)->test(Index::class);

        $page()->call('setFrequency', $mine->id, 'weekly');
        expect($mine->fresh()->frequency)->toBe('weekly');
        $page()->call('setFrequency', $mine->id, 'hourly')->assertStatus(422);
        $page()->call('setFrequency', $theirs->id, 'weekly')->assertNotFound();
        $page()->call('openSearch', $theirs->id)->assertNotFound();

        Livewire::actingAs($user)->test(Index::class)->call('deleteSearch', $theirs->id)->call('deleteSearch', $mine->id);
        expect(SavedSearch::query()->pluck('what')->all())->toBe(['Go']);
    });
});

describe('Job alerts', function (): void {
    it('emails the new jobs that match, best fit first, and then waits for the next day', function (): void {
        Notification::fake();
        saveJobs([['title' => 'Old PHP Developer', 'workMode' => 'remote', 'externalId' => 'old-1', 'company' => 'Elbwerk']]);
        $user = backendCandidate();
        savedSearch($user, ['mode' => 'remote']);

        $this->travel(2)->hours();
        saveJobs([
            ['title' => 'Remote Office Manager', 'workMode' => 'remote'],
            ['title' => 'Remote PHP Developer', 'workMode' => 'remote', 'description' => 'Laravel.'],
            ['title' => 'PHP Developer on site', 'workMode' => 'onsite'],
            ['title' => 'Remote Sales Lead', 'workMode' => 'remote'],
        ]);
        $user->dismissedListings()->create(['job_listing_id' => JobListing::query()->where('title', 'Remote Sales Lead')->value('id'), 'title' => 'Remote Sales Lead']);

        $this->travel(1)->day();
        $this->artisan('careercraft:send-job-alerts')->expectsOutput('Sent 1 job alerts.')->assertSuccessful();
        $alert = Notification::sent($user, JobAlert::class)->sole();
        expect(alertedTitles($user))->toBe([['Remote PHP Developer', 'Remote Office Manager']])
            ->and($alert->total)->toBe(2)
            ->and($alert->jobs[0]['skills'])->toBe(['PHP', 'Laravel'])
            ->and($alert->jobs[0]['url'])->toStartWith('https://www.arbeitnow.com/jobs/');

        // Checked now: the same jobs aren't sent again, and the next check is tomorrow.
        $this->travel(2)->hours();
        saveJobs([['title' => 'Remote Laravel Developer', 'workMode' => 'remote', 'externalId' => 'laravel-1', 'company' => 'Elbwerk']]);
        $this->artisan('careercraft:send-job-alerts')->expectsOutput('Sent 0 job alerts.');
        $this->travel(22)->hours();
        $this->artisan('careercraft:send-job-alerts')->expectsOutput('Sent 1 job alerts.');
        expect(alertedTitles($user)[1])->toBe(['Remote Laravel Developer']);
    });

    it('sends a weekly search once a week, and nothing when nothing is new', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        $weekly = savedSearch($user, ['frequency' => 'weekly']);
        $daily = savedSearch($user, ['what' => 'Nothing like this exists']);

        $this->travel(1)->hour();
        saveJobs([['title' => 'Backend Developer']]);
        $this->travel(1)->day();
        $this->artisan('careercraft:send-job-alerts');
        Notification::assertNothingSent();
        expect($daily->fresh()->checked_at->isSameMinute(now()))->toBeTrue()
            ->and($weekly->fresh()->checked_at->isSameMinute(now()))->toBeFalse();

        $this->travel(6)->days();
        $this->artisan('careercraft:send-job-alerts');
        expect(alertedTitles($user))->toBe([['Backend Developer']]);
    });

    it('lists the newest first for a profile with nothing to rank by', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        savedSearch($user);
        $this->travel(1)->hour();
        saveJobs([['title' => 'Newer'], ['title' => 'Older']]);

        $this->travel(1)->day();
        $this->artisan('careercraft:send-job-alerts');
        expect(alertedTitles($user))->toBe([['Newer', 'Older']]);
    });

    it('emails only verified addresses', function (): void {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        savedSearch($user);
        $this->travel(1)->hour();
        saveJobs([['title' => 'Backend Developer']]);

        $this->travel(1)->day();
        $this->artisan('careercraft:send-job-alerts');
        Notification::assertNothingSent();
    });

    it('writes the email in the person\'s language, with a link to the whole search', function (): void {
        Notification::fake();
        $user = backendCandidate();
        $user->update(['locale' => 'de']);
        savedSearch($user, ['what' => 'PHP', 'company' => 'Kranich Mobility GmbH']);
        $this->travel(1)->hour();
        saveJobs(array_map(fn (int $i): array => ['title' => "PHP Developer {$i}", 'company' => 'Kranich Mobility GmbH'], range(1, 12)));

        $this->travel(1)->day();
        $this->artisan('careercraft:send-job-alerts');
        Notification::assertSentTo($user, JobAlert::class, fn (JobAlert $alert, array $channels, object $notifiable, ?string $locale): bool => $locale === 'de');

        app()->setLocale('de');
        $alert = Notification::sent($user, JobAlert::class)->sole();
        $mail = $alert->toMail($user);
        $html = (string) $mail->render();
        expect($alert->jobs)->toHaveCount(SavedSearch::ALERT_JOBS)
            ->and($mail->subject)->toBe('12 neue Jobs für „PHP · bei Kranich Mobility GmbH“')
            ->and($html)->toContain('href="https://www.arbeitnow.com/jobs/backend-developer-kranich-1"', 'PHP Developer 1', 'Ihre Fähigkeiten: PHP', 'Und 2 weitere.', e(route('discover', ['what' => 'PHP', 'company' => 'Kranich Mobility GmbH'])), 'jeden Tag');
    });

    it('fetches each saved search from the job sources in the daily refresh', function (): void {
        Queue::fake();
        savedSearch(User::factory()->create(), ['what' => 'PHP', 'where' => 'Hamburg']);
        savedSearch(User::factory()->create(), ['what' => 'PHP', 'where' => 'Hamburg', 'mode' => 'remote']);
        savedSearch(User::factory()->create(), ['what' => 'Go']);

        $this->artisan('careercraft:refresh-jobs')->expectsOutputToContain('2 saved searches');

        // Arbeitnow's feed is the same for every search, so it is fetched once.
        expect(Queue::pushed(ImportJobListings::class, fn (ImportJobListings $job): bool => $job->source === 'arbeitnow'))->toHaveCount(1)
            ->and(Queue::pushed(ImportJobListings::class, fn (ImportJobListings $job): bool => $job->source === 'arbeitsagentur')->map(fn (ImportJobListings $job): string => "{$job->what}|{$job->where}")->sort()->values()->all())
            ->toBe(['Go|', 'PHP|Hamburg']);
    });
});

describe('Company pages', function (): void {
    it('shows one company\'s jobs, where and how it hires, and whether its board is followed', function (): void {
        saveJobs([
            ['title' => 'Backend Developer', 'company' => 'Kranich Mobility GmbH', 'location' => 'Hamburg', 'workMode' => 'hybrid'],
            ['title' => 'Data Engineer', 'company' => 'Kranich Mobility GmbH', 'location' => 'Hamburg', 'workMode' => 'remote'],
            ['title' => 'Designer', 'company' => 'Kranich Mobility GmbH', 'location' => 'Kiel'],
            ['title' => 'Office Manager', 'company' => 'Hafenblick Studio'],
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)
            ->assertSeeHtml(e(route('discover', ['company' => 'Kranich Mobility GmbH'])));

        $page = Livewire::actingAs($user)->withQueryParams(['company' => 'Kranich Mobility GmbH'])->test(Index::class)
            ->assertSee(['3 open jobs', 'Where: Hamburg, Kiel', 'Work mode: Hybrid 1 · Remote 1', 'Save this search to get an email'])
            ->assertDontSee('Office Manager');
        expect(collect($page->viewData('listings')->items())->pluck('title')->all())->toBe(['Backend Developer', 'Data Engineer', 'Designer']);

        $user->followedBoards()->create(['board' => 'greenhouse', 'slug' => 'kranich', 'name' => 'Kranich Mobility GmbH']);
        $page->call('$refresh')->assertSee('You follow its job board');
        $page->call('$set', 'company', '')->assertSee('Office Manager')->assertDontSee('open jobs');
    });
});

it('names a saved search by what it looks for', function (): void {
    expect((new ListingFilters)->label())->toBe('All jobs')
        ->and((new ListingFilters('PHP', 'Berlin', false, 'hybrid', true, 'Kranich'))->label())->toBe('PHP · at Kranich · Berlin · Hybrid · with a salary')
        ->and((new ListingFilters(company: 'Kranich'))->label())->toBe('Jobs at Kranich')
        ->and((new ListingFilters('PHP', 'Berlin', false, 'hybrid', true, 'Kranich'))->parameters())->toBe(['what' => 'PHP', 'where' => 'Berlin', 'remote' => 0, 'mode' => 'hybrid', 'salary' => 1, 'company' => 'Kranich'])
        ->and((new ListingFilters)->parameters())->toBe([]);
});
