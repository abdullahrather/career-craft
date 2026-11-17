<?php

declare(strict_types=1);

use App\Livewire\Applications\Board;
use App\Livewire\Applications\Contacts;
use App\Livewire\Applications\Notes;
use App\Livewire\Applications\Show;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** The timeline's sentences, oldest first. */
function timeline(Application $application): array
{
    return $application->events()->reorder('id')->get()->map(fn (ApplicationEvent $event): string => $event->text())->all();
}

describe('The timeline', function (): void {
    it('records what happens to an application, in order', function (): void {
        Storage::fake('local');
        Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 letter', 200, ['Content-Type' => 'application/pdf'])]);
        $user = candidate();
        $application = Application::track($user, ['title' => 'Product Designer', 'source' => 'StepStone']);

        Livewire::actingAs($user)->test(Board::class)->call('move', $application->id, 0, 'applied');
        // A move within the same column isn't news.
        Livewire::actingAs($user)->test(Board::class)->call('move', $application->id, 0, 'applied');
        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->set(['cvVersion' => (string) savedVersion($user)->id, 'letter' => (string) readyLetter($user)->id])->call('recordDocuments')
            ->set('status', 'interviewing')->call('save');
        Livewire::actingAs($user)->test(Notes::class, ['applicationId' => $application->id])->set('body', 'Call went well.')->call('add');
        Livewire::actingAs($user)->test(Contacts::class, ['applicationId' => $application->id])->set('name', 'Maren Vogt')->call('add');

        expect(timeline($application))->toBe([
            'Added to Saved, found on StepStone',
            'Moved from Saved to Applied',
            'Recorded what was sent: Design roles, version 1, and the letter “Application as Product Designer”',
            'Moved from Applied to Interviewing',
            'Added a note',
            'Added Maren Vogt as a contact',
        ]);

        Livewire::actingAs($user)->test(Show::class, ['id' => $application->id])
            ->assertSeeInOrder(['Timeline', 'Added Maren Vogt as a contact', 'Added to Saved, found on StepStone']);
    });

    it('notes taking back the documents, but not choosing none when nothing was recorded', function (): void {
        Storage::fake('local');
        $user = candidate();
        $application = Application::track($user, ['title' => 'Product Designer']);
        $page = Livewire::actingAs($user)->test(Show::class, ['id' => $application->id]);

        $page->call('recordDocuments');
        expect(timeline($application))->toBe(['Added to Saved']);

        $page->set('cvVersion', (string) savedVersion($user)->id)->call('recordDocuments')->set('cvVersion', '')->call('recordDocuments');
        expect(array_slice(timeline($application), 1))->toBe(['Recorded what was sent: Design roles, version 1', 'Removed the record of what was sent']);
    });

    it('reads in the reader\'s language', function (): void {
        $application = Application::track(User::factory()->create(), ['title' => 'Product Designer']);
        $application->moveTo('offer');

        app()->setLocale('de');
        expect(timeline($application))->toBe(['Zu „Gemerkt“ hinzugefügt', 'Von „Gemerkt“ nach „Angebot“ verschoben']);
    });
});

describe('Contacts', function (): void {
    it('adds and removes the people behind an application', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer']);

        Livewire::actingAs($user)->test(Contacts::class, ['applicationId' => $application->id])
            ->set(['name' => ' Maren Vogt ', 'role' => 'Recruiter', 'email' => 'maren@nordwind.example', 'phone' => '+49 421 123456', 'linkedin' => 'https://www.linkedin.com/in/maren-vogt'])
            ->call('add')->assertHasNoErrors()->assertSet('name', '')
            ->assertSee(['Maren Vogt', 'Recruiter', 'mailto:maren@nordwind.example', 'tel:+49421123456', 'https://www.linkedin.com/in/maren-vogt'], false);

        $contact = $application->contacts()->sole();
        expect($contact->only(['name', 'role', 'email', 'phone']))->toBe(['name' => 'Maren Vogt', 'role' => 'Recruiter', 'email' => 'maren@nordwind.example', 'phone' => '+49 421 123456']);

        Livewire::actingAs($user)->test(Contacts::class, ['applicationId' => $application->id])->call('remove', $contact->id);
        expect($application->contacts()->count())->toBe(0);
    });

    it('needs a name, and checks the email, phone and LinkedIn link', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer']);

        Livewire::actingAs($user)->test(Contacts::class, ['applicationId' => $application->id])
            ->set(['name' => '', 'email' => 'not an email', 'phone' => 'call me', 'linkedin' => 'javascript:alert(1)'])->call('add')
            ->assertHasErrors(['name' => 'required', 'email', 'phone', 'linkedin']);
    });
});

describe('Notes', function (): void {
    it('adds notes, newest first, and deletes them', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer']);
        $notes = Livewire::actingAs($user)->test(Notes::class, ['applicationId' => $application->id]);

        $notes->set('body', '  Ask about the team size.  ')->call('add')->assertSet('body', '');
        $notes->set('body', "Phone call with Maren.\nSecond round next week.")->call('add')
            ->assertSeeInOrder(['Second round next week.', 'Ask about the team size.']);
        expect($application->notes()->pluck('body')->all())->toBe(["Phone call with Maren.\nSecond round next week.", 'Ask about the team size.']);

        $notes->call('remove', $application->notes()->value('id'));
        expect($application->notes()->pluck('body')->all())->toBe(['Ask about the team size.']);
    });

    it('needs some text, up to 5000 characters', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer']);

        Livewire::actingAs($user)->test(Notes::class, ['applicationId' => $application->id])
            ->set('body', '   ')->call('add')->assertHasErrors(['body' => 'required'])
            ->set('body', str_repeat('a', 5001))->call('add')->assertHasErrors(['body' => 'max']);
    });
});

it('keeps contacts and notes to the application\'s owner', function (): void {
    $application = Application::track(User::factory()->create(), ['title' => 'Product Designer']);
    $note = $application->notes()->create(['body' => 'Private.']);
    $contact = $application->contacts()->create(['name' => 'Maren Vogt']);
    $other = User::factory()->create();

    Livewire::actingAs($other)->test(Notes::class, ['applicationId' => $application->id])->assertNotFound();
    Livewire::actingAs($other)->test(Contacts::class, ['applicationId' => $application->id])->assertNotFound();
    expect($note->fresh())->not->toBeNull()
        ->and($contact->fresh())->not->toBeNull();
});
