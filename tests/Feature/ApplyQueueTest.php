<?php

declare(strict_types=1);

use App\Ai\AiSettings;
use App\Jobs\AnalyseJobPost;
use App\Jobs\PrepareApplication;
use App\Jobs\TailorText;
use App\Jobs\WriteCoverLetter;
use App\Livewire\ApplyQueue\Index as QueuePage;
use App\Livewire\ApplyQueue\Review;
use App\Livewire\Discover\Index as FindJobs;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\ApplyQueueItem;
use App\Models\CoverLetter;
use App\Models\JobListing;
use App\Models\JobPost;
use App\Models\TailoredChange;
use App\Models\User;
use App\Resumes\Tailoring;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A fictional candidate with a CV chosen for the apply queue. */
function queueCandidate(): User
{
    $user = candidate();
    $user->forceFill(['apply_resume_id' => resumeFor($user)->id, 'apply_tone' => 'warm'])->save();

    return $user;
}

function analysedPost(User $user, array $values = []): JobPost
{
    return $user->jobPosts()->create(['status' => JobPost::READY, 'url' => 'https://www.arbeitnow.com/jobs/product-designer-nordwind', 'text' => 'Product Designer at Nordwind Logistik.', 'title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'location' => 'Bremen', 'language' => 'de', 'required_skills' => ['Figma'], 'nice_skills' => [], ...$values]);
}

/** A queued job prepared as far as the AI takes it: a tailored CV with one suggestion, and a letter. */
function preparedItem(User $user, string $suggestion = TailoredChange::ACCEPTED, array $letter = []): ApplyQueueItem
{
    Queue::fake();
    $post = analysedPost($user);
    $resume = Tailoring::start($user->resumes()->findOrFail($user->apply_resume_id), $post);
    $resume->changes()->update(['status' => $suggestion, 'suggestion' => 'Raised task completion by 25% through research', 'checked' => true]);
    $coverLetter = $user->coverLetters()->create(['job_post_id' => $post->id, 'resume_id' => $resume->id, 'language' => 'de', 'tone' => 'warm', 'length' => 'standard', 'recipient' => "Nordwind Logistik\nBremen", 'subject' => 'Bewerbung als Product Designer', 'body' => 'Ich gestalte Werkzeuge für Depots.', 'checked' => true, 'status' => CoverLetter::READY, ...$letter]);

    return $user->applyQueueItems()->create(['job_post_id' => $post->id, 'base_resume_id' => $user->apply_resume_id, 'resume_id' => $resume->id, 'cover_letter_id' => $coverLetter->id, 'title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'tone' => 'warm', 'prepared_at' => now()]);
}

describe('Queueing jobs', function (): void {
    it('queues a job from Find jobs, to be analysed and then prepared', function (): void {
        Queue::fake();
        saveJobs([['title' => 'Product Designer', 'company' => 'Nordwind Logistik']]);
        $user = queueCandidate();
        $listing = JobListing::query()->sole();

        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listing->id)
            ->assertHasNoErrors()->assertSee('Queued: Product Designer.')->assertSee('Queued ✓', false);

        $item = $user->applyQueueItems()->sole();
        expect($item->only(['job_listing_id', 'base_resume_id', 'title', 'company', 'tone']))->toBe(['job_listing_id' => $listing->id, 'base_resume_id' => $user->apply_resume_id, 'title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'tone' => 'warm'])
            ->and($item->state())->toBe('preparing');
        Queue::assertPushedWithChain(AnalyseJobPost::class, [PrepareApplication::class]);
    });

    it('needs a CV to start from, and takes each job once until it is skipped', function (): void {
        Queue::fake();
        saveJobs([['title' => 'Product Designer']]);
        $listing = JobListing::query()->sole();
        $user = candidate();

        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listing->id)->assertHasErrors('queue')->assertSee('Choose the CV to start from');

        $user->forceFill(['apply_resume_id' => resumeFor($user)->id])->save();
        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listing->id)->call('queue', $listing->id)->assertSee('This job is in your apply queue already.');
        $user->applyQueueItems()->update(['skipped_at' => now()]);
        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listing->id)->assertHasNoErrors();

        expect($user->applyQueueItems()->count())->toBe(2);
    });

    it('queues 10 jobs a day, and none once the AI allowance is used', function (): void {
        Queue::fake();
        saveJobs(array_map(fn (int $i): array => ['title' => "Job {$i}"], range(1, 11)));
        $user = queueCandidate();
        $listings = JobListing::query()->orderBy('id')->get();

        foreach ($listings->take(10) as $listing) {
            Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listing->id)->assertHasNoErrors();
        }
        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listings->last()->id)->assertSee('You can queue 10 jobs a day.');

        $this->travel(1)->day();
        (new AiSettings(dailyTokens: 100))->save();
        useToday($user, 100);
        Livewire::actingAs($user)->test(FindJobs::class)->call('queue', $listings->last()->id)->assertHasErrors('queue');
        expect($user->applyQueueItems()->count())->toBe(10);
    });
});

describe('Preparing', function (): void {
    it('tailors the CV and writes a letter in the post\'s language once the post is read', function (): void {
        Queue::fake();
        $user = queueCandidate();
        $post = analysedPost($user);
        $item = $user->applyQueueItems()->create(['job_post_id' => $post->id, 'base_resume_id' => $user->apply_resume_id, 'title' => 'Designer', 'tone' => 'warm']);

        (new PrepareApplication($item))->handle();
        $item->refresh();

        expect($item->resume->job_post_id)->toBe($post->id)
            ->and($item->coverLetter->only(['language', 'tone', 'resume_id', 'status']))->toBe(['language' => 'de', 'tone' => 'warm', 'resume_id' => $item->resume_id, 'status' => CoverLetter::WRITING])
            ->and($item->title)->toBe('Product Designer')
            ->and($item->prepared_at)->not->toBeNull()
            ->and($item->state())->toBe('preparing');
        Queue::assertPushed(TailorText::class);
        Queue::assertPushed(WriteCoverLetter::class);
    });

    it('stops when the post can\'t be read or the CV to start from is gone', function (): void {
        Queue::fake();
        $user = queueCandidate();
        $unread = $user->applyQueueItems()->create(['job_post_id' => analysedPost($user, ['status' => JobPost::FAILED])->id, 'base_resume_id' => $user->apply_resume_id, 'title' => 'Designer', 'tone' => 'warm']);
        $noCv = $user->applyQueueItems()->create(['job_post_id' => analysedPost($user)->id, 'base_resume_id' => null, 'title' => 'Designer', 'tone' => 'warm']);

        (new PrepareApplication($unread))->handle();
        (new PrepareApplication($noCv))->handle();

        expect($unread->fresh()->state())->toBe('failed')
            ->and($unread->fresh()->resume_id)->toBeNull()
            ->and($noCv->fresh()->error)->toBe('no-cv')
            ->and($noCv->fresh()->state())->toBe('failed');
        Queue::assertNotPushed(WriteCoverLetter::class);
    });

    it('shows as stopped, not preparing for ever, when preparing fails', function (): void {
        Queue::fake();
        $user = queueCandidate();
        $item = $user->applyQueueItems()->create(['job_post_id' => analysedPost($user)->id, 'base_resume_id' => $user->apply_resume_id, 'title' => 'Designer', 'tone' => 'warm']);

        (new PrepareApplication($item))->failed(new RuntimeException('The database went away.'));

        expect($item->fresh()->state())->toBe('failed');
        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->assertSee('Preparing this application failed.');
    });

    it('is ready once the AI is done, and stopped if the letter is deleted', function (): void {
        $user = queueCandidate();
        $item = preparedItem($user, TailoredChange::WAITING);
        expect($item->state())->toBe('preparing');

        $item->resume->changes()->update(['status' => TailoredChange::READY]);
        expect($item->fresh()->state())->toBe('ready');

        $item->coverLetter->delete();
        expect($item->fresh()->state())->toBe('failed');
    });
});

describe('Approving', function (): void {
    it('saves the CV version and adds an application in Applied with copies of what is sent', function (): void {
        Storage::fake('local');
        Http::fake(['gotenberg:3000/*' => Http::response(pdfWith(['Lena Brandt', 'Product designer']), 200, ['Content-Type' => 'application/pdf'])]);
        $user = queueCandidate();
        $item = preparedItem($user);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])
            ->assertSee(['Approve', 'Tailored to a job'])
            ->call('approve')->assertHasNoErrors()
            ->assertSee(['Approved', 'Apply on the job\'s page', 'https://www.arbeitnow.com/jobs/product-designer-nordwind']);

        $item->refresh();
        $application = $item->application;
        expect($item->state())->toBe('approved')
            ->and($application->only(['title', 'company', 'status', 'source', 'job_post_id']))->toBe(['title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'status' => 'applied', 'source' => 'arbeitnow.com', 'job_post_id' => $item->job_post_id])
            ->and($application->cv_name)->toContain('version 1')
            ->and($application->letter_name)->toBe('Bewerbung als Product Designer')
            ->and($item->resume->versions()->count())->toBe(1)
            ->and($application->events()->reorder('id')->get()->map(fn (ApplicationEvent $event): string => $event->type)->all())->toBe(['added', 'documents', 'queue', 'status']);
        Storage::disk('local')->assertExists([$application->cv_path, $application->letter_path]);
    });

    it('uses the application already tracked for the job', function (): void {
        Storage::fake('local');
        Http::fake(['gotenberg:3000/*' => Http::response(pdfWith(['Lena Brandt']), 200, ['Content-Type' => 'application/pdf'])]);
        $user = queueCandidate();
        $item = preparedItem($user);
        $tracked = Application::track($user, ['title' => 'Product Designer', 'job_post_id' => $item->job_post_id]);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('approve')->assertHasNoErrors();

        expect($user->applications()->sole()->id)->toBe($tracked->id)
            ->and($tracked->fresh()->status)->toBe('applied');
    });

    it('waits for a finished letter, and for the AI to be done', function (): void {
        Storage::fake('local');
        $user = queueCandidate();
        $unconfirmed = preparedItem($user, letter: ['body' => 'Ich gestalte Werkzeuge für 40 Depots.', 'flags' => [['quote' => '40', 'kind' => 'number']]]);
        $preparing = preparedItem($user, TailoredChange::WAITING);

        Livewire::actingAs($user)->test(Review::class, ['id' => $unconfirmed->id])->call('approve')->assertHasErrors('approve')->assertSee('Finish the letter first');
        Livewire::actingAs($user)->test(Review::class, ['id' => $preparing->id])->call('approve')->assertHasErrors('approve');

        expect($user->applications()->count())->toBe(0)
            ->and($unconfirmed->fresh()->approved_at)->toBeNull();
    });

    it('notes suggestions left undecided, which keep the person\'s own words', function (): void {
        $user = queueCandidate();
        $item = preparedItem($user, TailoredChange::READY);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->assertSee('neither accepted nor rejected');
    });
});

describe('The queue pages', function (): void {
    it('lists the queue with each job\'s state, and logs what was approved or skipped', function (): void {
        $user = queueCandidate();
        $ready = preparedItem($user);
        $preparing = preparedItem($user, TailoredChange::WAITING);
        $skipped = preparedItem($user);
        $skipped->update(['skipped_at' => now(), 'title' => 'Skipped Designer']);

        Livewire::actingAs($user)->test(QueuePage::class)
            ->assertSee(['Ready to review', 'Preparing', 'Log', 'Skipped Designer', 'Skipped', '3 of 10 queued today.']);

        Livewire::actingAs($user)->test(Review::class, ['id' => $ready->id])->call('skip')->assertRedirect(route('apply-queue'));
        expect($ready->fresh()->state())->toBe('skipped')
            ->and($preparing->fresh()->state())->toBe('preparing');
    });

    it('saves which CV and tone it prepares with, from the person\'s own CVs', function (): void {
        $user = candidate();
        $resume = resumeFor($user);

        Livewire::actingAs($user)->test(QueuePage::class)
            ->set(['resumeId' => (string) resumeFor(candidate())->id, 'tone' => 'formal'])->call('saveSettings')->assertHasErrors('resumeId')
            ->set(['resumeId' => (string) $resume->id, 'tone' => 'shouty'])->call('saveSettings')->assertHasErrors('tone')
            ->set(['resumeId' => (string) $resume->id, 'tone' => 'confident'])->call('saveSettings')->assertHasNoErrors()->assertSee('Saved.');

        expect($user->fresh()->only(['apply_resume_id', 'apply_tone']))->toBe(['apply_resume_id' => $resume->id, 'apply_tone' => 'confident']);
    });

    it('shows each person only their own queue', function (): void {
        $item = preparedItem(queueCandidate());

        $this->actingAs(candidate())->get(route('apply-queue.review', ['id' => $item->id]))->assertNotFound();
        Livewire::actingAs(candidate())->test(Review::class, ['id' => $item->id])->assertNotFound();
        $this->actingAs($item->user)->get(route('apply-queue'))->assertOk()->assertSee('Product Designer');
    });
});
