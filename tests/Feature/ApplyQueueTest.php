<?php

declare(strict_types=1);

use App\Ai\Agents\AnswerWriter;
use App\Ai\Agents\FactChecker;
use App\Ai\Agents\FactVerifier;
use App\Ai\AiSettings;
use App\Jobs\AnalyseJobPost;
use App\Jobs\PrepareApplication;
use App\Jobs\TailorText;
use App\Jobs\WriteAnswers;
use App\Jobs\WriteCoverLetter;
use App\Livewire\ApplyQueue\Index as QueuePage;
use App\Livewire\ApplyQueue\Review;
use App\Livewire\Discover\Index as FindJobs;
use App\Livewire\Profile\ApplicationDetails;
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
use Laravel\Ai\Prompts\AgentPrompt;
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

    it('saves the CV version once when approving is tried again after the letter\'s PDF failed', function (): void {
        Storage::fake('local');
        $pdf = Http::response(pdfWith(['Lena Brandt']), 200, ['Content-Type' => 'application/pdf']);
        Http::fakeSequence('gotenberg:3000/*')->pushResponse($pdf)->push('down', 503)->pushResponse($pdf);
        $user = queueCandidate();
        $item = preparedItem($user);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('approve')->assertHasErrors('approve');
        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('approve')->assertHasNoErrors();

        expect($item->resume->versions()->count())->toBe(1)
            ->and($item->fresh()->state())->toBe('approved');
    });

    it('saves a new version when the CV changed after a failed try', function (): void {
        Storage::fake('local');
        $pdf = Http::response(pdfWith(['Lena Brandt']), 200, ['Content-Type' => 'application/pdf']);
        Http::fakeSequence('gotenberg:3000/*')->pushResponse($pdf)->push('down', 503)->pushResponse($pdf)->pushResponse($pdf);
        $user = queueCandidate();
        $item = preparedItem($user, TailoredChange::READY);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('approve')->assertHasErrors('approve');
        $this->travel(1)->minute();
        $item->resume->changes()->first()->update(['status' => TailoredChange::ACCEPTED]);
        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('approve')->assertHasNoErrors();

        expect($item->resume->versions()->count())->toBe(2)
            ->and($item->fresh()->application->resume_version_id)->toBe($item->resume->versions()->first()->id);
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

describe('Answers to common questions', function (): void {
    beforeEach(function (): void {
        FactChecker::fake(fn (): array => ['claims' => []]);
        FactVerifier::fake(fn (): array => ['fact' => '', 'reason' => '', 'backed' => false]);
    });

    it('are written while the job is prepared, in the post\'s language, and checked against the profile', function (): void {
        Queue::fake();
        $user = queueCandidate();
        $item = $user->applyQueueItems()->create(['job_post_id' => analysedPost($user)->id, 'base_resume_id' => $user->apply_resume_id, 'title' => 'Designer', 'tone' => 'warm']);
        (new PrepareApplication($item))->handle();
        Queue::assertPushed(WriteAnswers::class);
        expect($item->fresh()->answers_status)->toBe('writing');

        AnswerWriter::fake([['why_company' => 'Bei Nordwind Logistik möchte ich Werkzeuge gestalten, wie zuvor für Google.', 'why_fit' => 'Bei Hafenblick Studio habe ich die Abschlussrate um 25% erhöht.']]);
        FactChecker::fake(fn (): array => ['claims' => [['quote' => 'Google', 'kind' => 'employer'], ['quote' => 'bei Nordwind Logistik', 'kind' => 'employer']]]);
        app()->call([new WriteAnswers($item->fresh()), 'handle']);

        $item->refresh();
        expect($item->answers_status)->toBe('ready')
            ->and(array_column($item->answers, 'question'))->toBe(['why_company', 'why_fit'])
            ->and($item->answers[1]['text'])->toBe('Bei Hafenblick Studio habe ich die Abschlussrate um 25% erhöht.')
            // The company applied to may be named; anything else not in the profile is kept.
            ->and($item->answers[0]['flags'])->toBe([['quote' => 'Google', 'kind' => 'employer']])
            ->and($item->answers[0]['checked'])->toBeTrue();
        AnswerWriter::assertPrompted(fn (AgentPrompt $prompt): bool => str_starts_with($prompt->prompt, "<job>Product Designer</job>\n<company>Nordwind Logistik</company>\n<skills>Figma</skills>\n<facts>\n")
            && str_ends_with($prompt->prompt, 'Write in German.'));
    });

    it('fail visibly when the AI gives nothing back, and the job is still ready to review', function (): void {
        $user = queueCandidate();
        $item = preparedItem($user);
        $item->update(['answers_status' => 'writing']);
        expect($item->fresh()->state())->toBe('preparing');

        AnswerWriter::fake([['why_company' => 'Something.', 'why_fit' => '  ']]);
        app()->call([new WriteAnswers($item->fresh()), 'handle']);

        expect($item->fresh()->answers_status)->toBe('failed')
            ->and($item->fresh()->state())->toBe('ready');
        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->assertSee('The AI could not write the answers.');

        (new WriteAnswers($item->fresh()))->failed(new RuntimeException('Timed out.'));
        expect($item->fresh()->answers_status)->toBe('failed');
    });

    it('shows the answers and the profile\'s facts ready to copy, with what the check couldn\'t find', function (): void {
        $user = queueCandidate();
        $user->careerProfile()->update(['salary_expectation' => 65000, 'notice_period' => '3 months to the end of a month', 'earliest_start' => '2027-02-01']);
        $item = preparedItem($user);
        $item->update(['answers_status' => 'ready', 'answers' => [
            ['question' => 'why_company', 'text' => 'I want to design tools at Nordwind Logistik.', 'flags' => [['quote' => 'Google', 'kind' => 'employer']], 'checked' => true],
            ['question' => 'why_fit', 'text' => 'I raised task completion by 25%.', 'flags' => [], 'checked' => false],
        ]]);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])
            ->assertSee(['Answers to common questions', 'Why do you want to work at Nordwind Logistik?', 'I want to design tools at Nordwind Logistik.', 'Not found in your profile: “Google”.', 'could not be checked against your profile'])
            ->assertSeeHtml('data-text="I raised task completion by 25%."')
            ->assertSee(['Salary expectation', '€65,000 a year', '3 months to the end of a month', '1 February 2027', 'Add the rest in your career profile'])
            ->assertDontSee('Work permit');
    });

    it('can be written again without the review going back to preparing', function (): void {
        Queue::fake();
        $user = queueCandidate();
        $item = preparedItem($user);
        $item->update(['answers_status' => 'ready', 'answers' => [['question' => 'why_fit', 'text' => 'Old answer.', 'flags' => [], 'checked' => true]]]);

        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('rewriteAnswers')->assertSee('Writing the answers…');
        Queue::assertPushed(WriteAnswers::class);
        expect($item->fresh()->state())->toBe('ready');

        $item->update(['answers_status' => 'failed']);
        (new AiSettings(dailyTokens: 100))->save();
        useToday($user, 100);
        Livewire::actingAs($user)->test(Review::class, ['id' => $item->id])->call('rewriteAnswers')->assertHasErrors('answers');
        expect($item->fresh()->answers_status)->toBe('failed');
    });
});

it('keeps the application details on the profile, never on a CV', function (): void {
    $user = candidate();

    Livewire::actingAs($user)->test(ApplicationDetails::class)
        ->set(['salaryExpectation' => '0', 'earliestStart' => 'soon'])->call('save')->assertHasErrors(['salaryExpectation', 'earliestStart'])
        ->set(['salaryExpectation' => '65000', 'noticePeriod' => ' 3 months ', 'earliestStart' => '2027-02-01', 'workPermit' => 'EU citizen'])->call('save')->assertHasNoErrors()->assertSee('Saved.');

    $profile = $user->careerProfile()->fresh();
    expect($profile->only(['salary_expectation', 'notice_period', 'work_permit']))->toBe(['salary_expectation' => 65000, 'notice_period' => '3 months', 'work_permit' => 'EU citizen'])
        ->and($profile->earliest_start->toDateString())->toBe('2027-02-01');
    cvPage($user, resumeFor($user)->id)->assertDontSee('65000')->assertDontSee('EU citizen');

    Livewire::actingAs($user)->test(ApplicationDetails::class)->set(['salaryExpectation' => '', 'earliestStart' => ''])->call('save');
    expect($user->careerProfile()->fresh()->salary_expectation)->toBeNull();
});
