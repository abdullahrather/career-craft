<?php

declare(strict_types=1);

use App\Livewire\Applications\Interviews;
use App\Livewire\Applications\Tasks;
use App\Livewire\Settings\Reminders;
use App\Models\Application;
use App\Models\User;
use App\Notifications\DailyReminder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** An application moved to Applied now, so it goes quiet from today. */
function appliedApplication(User $user, string $title = 'Product Designer', string $company = 'Nordwind Logistik'): Application
{
    $application = Application::track($user, ['title' => $title, 'company' => $company]);
    $application->moveTo('applied');

    return $application;
}

describe('Tasks', function (): void {
    it('adds tasks due from today, marks them done, and notes both on the timeline', function (): void {
        $user = User::factory()->create();
        $application = appliedApplication($user);
        $tasks = Livewire::actingAs($user)->test(Tasks::class, ['applicationId' => $application->id])->assertSet('dueOn', today()->toDateString());

        $tasks->set(['task' => ' Send a thank-you email ', 'dueOn' => today()->addDays(2)->toDateString()])->call('add')->assertHasNoErrors()->assertSet('task', '');
        $task = $application->tasks()->sole();
        expect($task->title)->toBe('Send a thank-you email');

        $tasks->call('toggle', $task->id);
        expect($task->fresh()->done_at)->not->toBeNull();
        $tasks->call('toggle', $task->id);
        expect($task->fresh()->done_at)->toBeNull()
            ->and($application->events()->pluck('type')->take(2)->all())->toBe(['task_done', 'task'])
            ->and($application->events()->first()->text())->toBe('Done: Send a thank-you email');

        $tasks->call('remove', $task->id);
        expect($application->tasks()->count())->toBe(0);
    });

    it('needs a title and a day from today on', function (): void {
        $user = User::factory()->create();
        $application = appliedApplication($user);

        Livewire::actingAs($user)->test(Tasks::class, ['applicationId' => $application->id])
            ->set(['task' => '', 'dueOn' => today()->subDay()->toDateString()])->call('add')
            ->assertHasErrors(['task' => 'required', 'dueOn']);
    });
});

describe('Interviews', function (): void {
    it('adds an interview and gives it as a calendar file at the time entered', function (): void {
        $user = User::factory()->create();
        $application = appliedApplication($user, 'Designer, Mobile', 'Nordwind Logistik');

        Livewire::actingAs($user)->test(Interviews::class, ['applicationId' => $application->id])
            ->set(['startsAt' => '2026-11-24T14:30', 'minutes' => '45', 'venue' => 'Am Wall 12; Raum 3\\B, Bremen'])->call('add')
            ->assertHasNoErrors()->assertSee(['Tuesday, 24 Nov 2026, 14:30', '45 minutes', 'Add to calendar']);
        $interview = $application->interviews()->sole();
        expect($application->events()->first()->text())->toBe('Interview on 24 Nov 2026, 14:30');

        $response = $this->actingAs($user)->get(route('applications.interview', ['id' => $application->id, 'interview' => $interview->id]))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="interview-2026-11-24.ics"');
        $ics = $response->getContent();
        $unfolded = str_replace("\r\n ", '', $ics);

        expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")->toEndWith("END:VCALENDAR\r\n")
            ->and($unfolded)->toContain("DTSTART:20261124T143000\r\n", "DTEND:20261124T151500\r\n", 'SUMMARY:Interview: Designer\, Mobile – Nordwind Logistik', 'LOCATION:Am Wall 12\; Raum 3\\\\B\, Bremen', "UID:interview-{$interview->id}@")
            ->and(collect(explode("\r\n", $ics))->every(fn (string $line): bool => strlen($line) <= 75))->toBeTrue();

        $this->actingAs(User::factory()->create())->get(route('applications.interview', ['id' => $application->id, 'interview' => $interview->id]))->assertNotFound();
    });

    it('folds long lines without splitting a character', function (): void {
        $user = User::factory()->create();
        $application = appliedApplication($user);
        $interview = $application->interviews()->create(['starts_at' => '2026-11-24 09:00', 'minutes' => 60, 'location' => str_repeat('Größenwahn ', 20)]);

        $lines = explode("\r\n", $interview->ics());
        expect(collect($lines)->every(fn (string $line): bool => strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8')))->toBeTrue()
            ->and(str_replace("\r\n ", '', $interview->ics()))->toContain('LOCATION:'.rtrim(str_repeat('Größenwahn ', 20)));
    });

    it('takes a length between 15 minutes and 8 hours', function (): void {
        $user = User::factory()->create();
        $application = appliedApplication($user);

        Livewire::actingAs($user)->test(Interviews::class, ['applicationId' => $application->id])
            ->set(['startsAt' => 'next week', 'minutes' => '10'])->call('add')
            ->assertHasErrors(['startsAt', 'minutes']);
    });
});

describe('The dashboard', function (): void {
    it('shows tasks due, interviews this week and applications to follow up', function (): void {
        $user = User::factory()->create();
        $quiet = appliedApplication($user, 'Product Designer');
        $busy = appliedApplication($user, 'UX Researcher', 'Hafenblick Studio');
        $busy->tasks()->create(['title' => 'Send the portfolio', 'due_on' => today()->addDays(8)]);
        $busy->tasks()->create(['title' => 'Already done', 'due_on' => today()->addDays(8), 'done_at' => now()]);
        $busy->interviews()->create(['starts_at' => today()->addDays(10)->setTime(10, 0), 'minutes' => 60]);

        $this->travel(8)->days();
        $busy->interviews()->create(['starts_at' => today()->addDays(2)->setTime(10, 0), 'minutes' => 60]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(['Tasks due', 'Send the portfolio', 'Due today', 'Interviews this week', 'UX Researcher', 'Time to follow up', 'Product Designer', 'No news for 8 days'])
            ->assertDontSee('Already done');

        // An open task means it's in hand; a new event starts the quiet days again.
        $this->travel(1)->day();
        $quiet->tasks()->create(['title' => 'Call Maren', 'due_on' => today()->addDay()]);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Overdue since')->assertDontSee('Time to follow up');
    });

    it('follows up after the days the person chose', function (): void {
        $user = User::factory()->create(['follow_up_days' => 3]);
        appliedApplication($user);

        $this->travel(2)->days();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Time to follow up');
        $this->travel(1)->day();
        $this->actingAs($user)->get(route('dashboard'))->assertSee(['Time to follow up', 'No news for 3 days']);
    });

    it('says when nothing needs attention', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Track the jobs you go for');

        Application::track($user, ['title' => 'Product Designer']);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Nothing due, no interviews this week');
    });
});

describe('The morning email', function (): void {
    it('sends tasks, interviews and follow-ups, and suggests a follow-up once per quiet spell', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        $quiet = appliedApplication($user, 'Product Designer');
        $other = appliedApplication($user, 'UX Researcher', 'Hafenblick Studio');
        $other->tasks()->create(['title' => 'Send the portfolio', 'due_on' => today()->addDays(8)]);
        $this->travel(8)->days();
        $other->interviews()->create(['starts_at' => today()->addDay()->setTime(10, 0), 'minutes' => 60, 'location' => 'Video call']);
        $updated = $quiet->fresh()->updated_at;

        $this->artisan('careercraft:send-reminders')->expectsOutput('Sent 1 reminder emails.');
        $mail = Notification::sent($user, DailyReminder::class)->sole();
        expect(array_column($mail->tasks, 'title'))->toBe(['Send the portfolio'])
            ->and(array_column($mail->interviews, 'title'))->toBe(['UX Researcher'])
            ->and($mail->quiet)->toBe([['title' => 'Product Designer', 'company' => 'Nordwind Logistik', 'days' => 8, 'url' => route('applications.show', ['id' => $quiet->id])]])
            ->and($quiet->fresh()->updated_at->equalTo($updated))->toBeTrue();

        // The next day only the quiet application is left, and it was mentioned already.
        $this->travel(1)->day();
        $other->tasks()->update(['done_at' => now()]);
        $other->interviews()->delete();
        $other->moveTo('interviewing');
        $this->artisan('careercraft:send-reminders')->expectsOutput('Sent 0 reminder emails.');

        // News, then quiet again: worth saying again.
        $quiet->log('note');
        $this->travel(7)->days();
        $this->artisan('careercraft:send-reminders')->expectsOutput('Sent 1 reminder emails.');
    });

    it('goes only to verified addresses that want it, and only with something to say', function (): void {
        Notification::fake();
        foreach ([User::factory()->unverified()->create(), User::factory()->create(['reminder_emails' => false])] as $user) {
            appliedApplication($user);
        }
        Application::track(User::factory()->create(), ['title' => 'Nothing due']);
        $this->travel(8)->days();

        $this->artisan('careercraft:send-reminders')->expectsOutput('Sent 0 reminder emails.');
        Notification::assertNothingSent();
    });

    it('writes the email in the person\'s language', function (): void {
        Notification::fake();
        $user = User::factory()->create(['locale' => 'de']);
        $application = appliedApplication($user);
        $application->tasks()->create(['title' => 'Mappe schicken', 'due_on' => today()]);
        $this->travel(1)->day();

        $this->artisan('careercraft:send-reminders');
        Notification::assertSentTo($user, DailyReminder::class, fn (DailyReminder $mail, array $channels, object $notifiable, ?string $locale): bool => $locale === 'de');

        app()->setLocale('de');
        $mail = Notification::sent($user, DailyReminder::class)->sole()->toMail($user);
        expect($mail->subject)->toBe('Ihre Bewerbungen heute')
            ->and((string) $mail->render())->toContain('Fällige Aufgaben', 'Mappe schicken', 'Überfällig seit', 'abschalten');
    });
});

it('saves how the person is reminded', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Reminders::class)->assertSet('followUpDays', '7')->assertSet('reminderEmails', true)
        ->set(['followUpDays' => '0'])->call('save')->assertHasErrors('followUpDays')
        ->set(['followUpDays' => '61'])->call('save')->assertHasErrors('followUpDays')
        ->set(['followUpDays' => '10', 'reminderEmails' => false])->call('save')->assertHasNoErrors()->assertSee('Saved.');

    expect($user->fresh()->only(['follow_up_days', 'reminder_emails']))->toBe(['follow_up_days' => 10, 'reminder_emails' => false]);
    $this->actingAs($user)->get(route('settings'))->assertSee('Days before a follow-up');
});
