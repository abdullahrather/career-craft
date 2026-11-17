<?php

declare(strict_types=1);

use App\Livewire\Applications\Board;
use App\Livewire\Applications\Show;
use App\Livewire\Discover\Index;
use App\Livewire\Jobs\Show as JobPage;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\JobListing;
use App\Models\JobPost;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Applications in a column, in the order the board shows them. */
function column(User $user, string $status): array
{
    return $user->applications()->where('status', $status)->orderBy('position')->orderBy('id')->pluck('title')->all();
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

describe('The board', function (): void {
    it('shows the person\'s applications in their columns', function (): void {
        $user = User::factory()->create();
        Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        Application::track(User::factory()->create(), ['title' => 'Someone else\'s job']);

        $this->actingAs($user)->get(route('applications'))->assertOk()
            ->assertSee(['Saved', 'Applied', 'Interviewing', 'Offer', 'Rejected', 'Product Designer', 'Nordwind Logistik'])
            ->assertDontSee('Someone else\'s job');
    });

    it('adds a job found elsewhere at the end of Saved', function (): void {
        $user = User::factory()->create();
        Application::track($user, ['title' => 'First']);

        Livewire::actingAs($user)->test(Board::class)
            ->set(['title' => ' UX Researcher ', 'company' => 'Hafenblick Studio', 'url' => 'https://example.com/jobs/1', 'source' => 'LinkedIn'])
            ->call('add')->assertHasNoErrors()->assertSet('title', '');

        expect(column($user, 'saved'))->toBe(['First', 'UX Researcher'])
            ->and($user->applications()->latest('id')->first()->only(['company', 'url', 'source']))->toBe(['company' => 'Hafenblick Studio', 'url' => 'https://example.com/jobs/1', 'source' => 'LinkedIn']);
    });

    it('needs a title, and only takes web links', function (): void {
        Livewire::actingAs(User::factory()->create())->test(Board::class)
            ->set(['title' => '', 'url' => 'javascript:alert(1)'])->call('add')
            ->assertHasErrors(['title' => 'required', 'url']);
    });

    it('moves a dropped card to its place in a column, and dates it on the first move to Applied', function (): void {
        $user = User::factory()->create();
        foreach (['A', 'B'] as $title) {
            Application::track($user, ['title' => $title])->moveTo('applied');
        }
        $card = Application::track($user, ['title' => 'C']);

        Livewire::actingAs($user)->test(Board::class)->call('move', $card->id, 1, 'applied');
        expect(column($user, 'applied'))->toBe(['A', 'C', 'B'])
            ->and($card->fresh()->applied_on->isToday())->toBeTrue();

        // Within a column, and the date stays as it was.
        $card->update(['applied_on' => '2026-11-01']);
        Livewire::actingAs($user)->test(Board::class)->call('move', $card->id, 0, 'applied')->call('move', $card->id, 0, 'interviewing');
        expect(column($user, 'applied'))->toBe(['A', 'B'])
            ->and(column($user, 'interviewing'))->toBe(['C'])
            ->and($card->fresh()->applied_on->toDateString())->toBe('2026-11-01');
    });

    it('moves a card with its status list to the end of that column', function (): void {
        $user = User::factory()->create();
        Application::track($user, ['title' => 'A'])->moveTo('offer');
        $card = Application::track($user, ['title' => 'B']);

        Livewire::actingAs($user)->test(Board::class)->call('setStatus', $card->id, 'offer');
        expect(column($user, 'offer'))->toBe(['A', 'B']);
    });

    it('takes known statuses and the person\'s own cards only', function (): void {
        $user = User::factory()->create();
        $mine = Application::track($user, ['title' => 'Mine']);
        $theirs = Application::track(User::factory()->create(), ['title' => 'Theirs']);

        Livewire::actingAs($user)->test(Board::class)->call('setStatus', $mine->id, 'ghosted')->assertStatus(422);
        Livewire::actingAs($user)->test(Board::class)->call('move', $theirs->id, 0, 'applied')->assertNotFound();
        expect($theirs->fresh()->status)->toBe('saved');
    });
});

describe('An application', function (): void {
    it('saves its details, and moves to the status chosen', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer']);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set(['title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'location' => 'Bremen', 'source' => 'StepStone', 'status' => 'applied'])
            ->call('save')->assertHasNoErrors()->assertSee('Saved.')
            ->assertSet('appliedOn', today()->toDateString());

        expect($application->fresh()->only(['title', 'company', 'location', 'source', 'status']))->toBe(['title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'location' => 'Bremen', 'source' => 'StepStone', 'status' => 'applied']);
    });

    it('rejects a date in the future and links that aren\'t web links', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Designer']);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set(['appliedOn' => today()->addDay()->toDateString(), 'url' => 'ftp://example.com', 'status' => 'hired'])->call('save')
            ->assertHasErrors(['appliedOn', 'url', 'status']);
    });

    it('keeps copies of the CV and letter sent, which outlive the CV and later edits', function (): void {
        Storage::fake('local');
        Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 letter', 200, ['Content-Type' => 'application/pdf'])]);
        $user = candidate();
        $version = savedVersion($user);
        $letter = readyLetter($user);
        $application = Application::track($user, ['title' => 'Product Designer']);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set(['cvVersion' => (string) $version->id, 'letter' => (string) $letter->id])->call('recordDocuments')
            ->assertHasNoErrors()->assertSee(['Recorded.', 'Design roles, version 1', 'Application as Product Designer']);

        $this->actingAs($user)->get(route('applications.download', ['id' => $application->id, 'document' => 'cv']))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=design-roles-version-1.pdf')
            ->assertStreamedContent('%PDF-1.7 cv');

        // The CV is deleted and the letter rewritten: the copies are what was sent.
        Storage::disk('local')->delete($version->pdf_path);
        $version->resume->delete();
        $letter->update(['body' => 'Something else entirely.']);
        $application->refresh();
        expect($application->resume_version_id)->toBeNull()
            ->and($application->cv_name)->toBe('Design roles, version 1');
        $this->actingAs($user)->get(route('applications.download', ['id' => $application->id, 'document' => 'cv']))->assertStreamedContent('%PDF-1.7 cv');
        $this->actingAs($user)->get(route('applications.download', ['id' => $application->id, 'document' => 'letter']))->assertStreamedContent('%PDF-1.7 letter');
        $this->actingAs(candidate())->get(route('applications.download', ['id' => $application->id, 'document' => 'letter']))->assertNotFound();

        $this->actingAs($user)->get(route('applications'))->assertSee(['CV', 'Letter']);
    });

    it('records nothing when the CV version\'s PDF is missing', function (): void {
        Storage::fake('local');
        $user = candidate();
        $version = savedVersion($user);
        Storage::disk('local')->delete($version->pdf_path);
        $application = Application::track($user, ['title' => 'Product Designer']);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set('cvVersion', (string) $version->id)->call('recordDocuments')
            ->assertHasErrors('cvVersion');

        expect($application->fresh()->cv_path)->toBeNull();
        Storage::disk('local')->assertMissing(Application::directory($application->id).'/cv.pdf');
    });

    it('keeps the copies recorded before when the letter\'s PDF can\'t be made', function (): void {
        Storage::fake('local');
        $user = candidate();
        $first = savedVersion($user, '%PDF-1.7 first');
        $application = Application::track($user, ['title' => 'Product Designer']);
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])->set('cvVersion', (string) $first->id)->call('recordDocuments');

        Http::fake(['gotenberg:3000/*' => Http::response('down', 503)]);
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set(['cvVersion' => (string) savedVersion($user, '%PDF-1.7 second')->id, 'letter' => (string) readyLetter($user)->id])->call('recordDocuments')
            ->assertHasErrors('letter');

        expect($application->fresh()->resume_version_id)->toBe($first->id);
        $this->actingAs($user)->get(route('applications.download', ['id' => $application->id, 'document' => 'cv']))->assertStreamedContent('%PDF-1.7 first');
    });

    it('takes back a document set to none', function (): void {
        Storage::fake('local');
        $user = candidate();
        $version = savedVersion($user);
        $application = Application::track($user, ['title' => 'Product Designer']);
        $page = Livewire::actingAs($user)->test(Show::class, ['id' => $application->id]);

        $page->set('cvVersion', (string) $version->id)->call('recordDocuments');
        $page->set('cvVersion', '')->call('recordDocuments');

        expect($application->fresh()->cv_path)->toBeNull();
        Storage::disk('local')->assertMissing(Application::directory($application->id).'/cv.pdf');
        $this->actingAs($user)->get(route('applications.download', ['id' => $application->id, 'document' => 'cv']))->assertNotFound();
    });

    it('records only a finished letter, and only the person\'s own documents', function (): void {
        Storage::fake('local');
        $user = candidate();
        $application = Application::track($user, ['title' => 'Product Designer']);
        $unconfirmed = readyLetter($user, ['body' => 'I design tools for 40 depots.', 'flags' => [['quote' => '40', 'kind' => 'number']]]);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set('letter', (string) $unconfirmed->id)->call('recordDocuments')
            ->assertHasErrors('letter');
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set('cvVersion', (string) savedVersion(candidate())->id)->call('recordDocuments')
            ->assertNotFound();
        expect($application->fresh()->letter_path)->toBeNull();
    });

    it('is deleted with its copies, and no one else can open it', function (): void {
        Storage::fake('local');
        $user = candidate();
        $application = Application::track($user, ['title' => 'Product Designer']);
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])->set('cvVersion', (string) savedVersion($user)->id)->call('recordDocuments');

        $this->actingAs(candidate())->get(route('applications.show', ['id' => $application->id]))->assertNotFound();
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])->call('delete')->assertRedirect(route('applications'));

        expect(Application::query()->count())->toBe(0);
        Storage::disk('local')->assertMissing(Application::directory($application->id));
    });
});

describe('Tracking a job', function (): void {
    it('tracks an analysed job post once, with where it came from', function (): void {
        $user = User::factory()->create();
        $post = $user->jobPosts()->create(['status' => JobPost::READY, 'url' => 'https://www.arbeitnow.com/jobs/designer-1', 'title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'location' => 'Bremen', 'language' => 'en', 'required_skills' => [], 'nice_skills' => []]);

        Livewire::actingAs($user)->test(JobPage::class, ['id' => $post->id])->assertSee('Track this job')->call('track');
        Livewire::actingAs($user)->test(JobPage::class, ['id' => $post->id])->assertSee('In your applications: Saved')->call('track');

        expect($user->applications()->sole()->only(['job_post_id', 'title', 'company', 'location', 'source', 'status']))
            ->toBe(['job_post_id' => $post->id, 'title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'location' => 'Bremen', 'source' => 'arbeitnow.com', 'status' => 'saved']);
    });

    it('tracks a job from Find jobs once, and marks it as tracked', function (): void {
        saveJobs([['title' => 'Backend Developer', 'company' => 'Kranich Mobility GmbH', 'location' => 'Hamburg']]);
        $user = User::factory()->create();
        $listing = JobListing::query()->sole();

        Livewire::actingAs($user)->test(Index::class)->assertSee('Track Backend Developer')
            ->call('track', $listing->id)->call('track', $listing->id)
            ->assertSee('Tracked');

        expect($user->applications()->sole()->only(['title', 'company', 'url', 'source']))
            ->toBe(['title' => 'Backend Developer', 'company' => 'Kranich Mobility GmbH', 'url' => 'https://www.arbeitnow.com/jobs/backend-developer-kranich-1', 'source' => 'Arbeitnow']);
    });

    it('takes a job link as long as the job sources allow', function (): void {
        $url = 'https://www.arbeitnow.com/jobs/'.str_repeat('a', 2048 - 31);
        saveJobs([['title' => 'Backend Developer', 'url' => $url]]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)->call('track', JobListing::query()->sole()->id);
        $application = $user->applications()->sole();
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])->call('save')->assertHasNoErrors();

        expect(strlen($application->url))->toBe(2048);
    });
});
