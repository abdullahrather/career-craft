<?php

declare(strict_types=1);

use App\Livewire\Profile\Completeness;
use App\Livewire\Profile\Experiences;
use App\Livewire\Profile\Languages;
use App\Livewire\Profile\Links;
use App\Livewire\Profile\PersonalDetails;
use App\Livewire\Profile\Skills;
use App\Models\User;
use Livewire\Livewire;

function job(array $overrides = []): array
{
    return [
        'title' => 'Backend developer',
        'employer' => 'Nordlicht Logistik',
        'city' => 'Hamburg',
        'country' => 'DE',
        'starts_on' => '2021-03',
        'ends_on' => '',
        'current' => true,
        'description' => '',
        'achievements' => "Cut order processing time by 40%\n\n  Led the move to PostgreSQL  ",
        ...$overrides,
    ];
}

describe('The profile page', function (): void {
    it('is for signed-in, confirmed people', function (): void {
        $this->get('/profile')->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->get('/profile')->assertRedirect('/email/verify');
    });

    it('shows every section, in German too', function (): void {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Karriereprofil')
            ->assertSee('Berufserfahrung')
            ->assertSee('Ausbildung')
            ->assertSee('Sprachen');
    });
});

describe('Personal details', function (): void {
    it('saves the headline, summary and contact details', function (): void {
        $user = User::factory()->create(['email' => 'lena@example.com']);

        Livewire::actingAs($user)->test(PersonalDetails::class)
            ->assertSet('email', 'lena@example.com')
            ->set('headline', '  Backend developer, PHP and Laravel ')
            ->set('city', 'Hamburg')
            ->set('country', 'DE')
            ->set('phone', '+49 40 1234567')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $profile = $user->careerProfile();
        expect($profile->headline)->toBe('Backend developer, PHP and Laravel')
            ->and($profile->country)->toBe('DE');
    });

    it('refuses an invalid email, phone or country', function (): void {
        Livewire::actingAs(User::factory()->create())->test(PersonalDetails::class)
            ->set('email', 'not an email')
            ->set('phone', 'call me maybe')
            ->set('country', 'XX')
            ->call('save')
            ->assertHasErrors(['email', 'phone', 'country']);
    });
});

describe('Work experience', function (): void {
    it('adds a current job, with one achievement per line', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Experiences::class)
            ->call('add')
            ->set('form', job())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('profile-changed');

        $saved = $user->careerProfile()->experiences()->sole();
        expect($saved->ends_on)->toBeNull()
            ->and($saved->starts_on->format('Y-m-d'))->toBe('2021-03-01')
            ->and($saved->achievements)->toBe(['Cut order processing time by 40%', 'Led the move to PostgreSQL']);
    });

    it('needs an end date unless it is the current job, and an end after the start', function (): void {
        $section = Livewire::actingAs(User::factory()->create())->test(Experiences::class)->call('add');

        $section->set('form', job(['current' => false, 'ends_on' => '']))->call('save')->assertHasErrors('form.ends_on');
        $section->set('form', job(['current' => false, 'ends_on' => '2020-01']))->call('save')->assertHasErrors('form.ends_on');
        $section->set('form', job(['starts_on' => now()->addYear()->format('Y-m')]))->call('save')->assertHasErrors('form.starts_on');
    });

    it('explains mistakes in German', function (): void {
        app()->setLocale('de');

        Livewire::actingAs(User::factory()->create())->test(Experiences::class)
            ->call('add')
            ->set('form', job(['title' => '']))
            ->call('save')
            ->assertHasErrors(['form.title' => 'Jobtitel muss ausgefüllt werden.']);
    });

    it('edits, reorders and deletes jobs', function (): void {
        $user = User::factory()->create();
        $section = Livewire::actingAs($user)->test(Experiences::class);
        foreach (['First', 'Second', 'Third'] as $title) {
            $section->call('add')->set('form', job(['title' => $title]))->call('save');
        }
        $titles = fn (): array => $user->careerProfile()->experiences()->pluck('title')->all();
        $id = fn (string $title): int => (int) $user->careerProfile()->experiences()->where('title', $title)->value('id');

        $section->call('move', $id('Third'), -1);
        expect($titles())->toBe(['First', 'Third', 'Second']);

        $section->call('move', $id('First'), -1);
        expect($titles())->toBe(['First', 'Third', 'Second']);

        $section->call('edit', $id('Second'))->assertSet('form.title', 'Second')
            ->set('form.title', 'Second, renamed')->call('save');
        $section->call('delete', $id('First'));
        expect($titles())->toBe(['Third', 'Second, renamed']);
    });

    it("can't touch someone else's jobs", function (): void {
        $owner = User::factory()->create();
        Livewire::actingAs($owner)->test(Experiences::class)->call('add')->set('form', job())->call('save');
        $theirs = (int) $owner->careerProfile()->experiences()->value('id');

        Livewire::actingAs(User::factory()->create())->test(Experiences::class)
            ->call('edit', $theirs)->assertNotFound();
        Livewire::actingAs(User::factory()->create())->test(Experiences::class)
            ->call('delete', $theirs)->assertNotFound();

        expect($owner->careerProfile()->experiences()->count())->toBe(1);
    });
});

describe('Skills, languages and links', function (): void {
    it('keeps each skill once per profile, but other people can list the same skill', function (): void {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Skills::class)
            ->call('add')->set('form', ['name' => 'Laravel', 'level' => 4])->call('save')->assertHasNoErrors()
            ->call('add')->set('form', ['name' => 'Laravel', 'level' => 2])->call('save')->assertHasErrors('form.name');

        Livewire::actingAs(User::factory()->create())->test(Skills::class)
            ->call('add')->set('form', ['name' => 'Laravel', 'level' => 3])->call('save')->assertHasNoErrors();
    });

    it('takes CEFR levels or native, and nothing else', function (): void {
        Livewire::actingAs(User::factory()->create())->test(Languages::class)
            ->call('add')->set('form', ['name' => 'German', 'level' => 'fluent'])->call('save')->assertHasErrors('form.level')
            ->set('form', ['name' => 'German', 'level' => 'C1'])->call('save')->assertHasNoErrors();
    });

    it('only takes web addresses as links', function (): void {
        Livewire::actingAs(User::factory()->create())->test(Links::class)
            ->call('add')->set('form', ['label' => 'Sneaky', 'url' => 'javascript:alert(1)'])->call('save')->assertHasErrors('form.url')
            ->set('form', ['label' => 'GitHub', 'url' => 'https://github.com/example'])->call('save')->assertHasNoErrors();
    });
});

describe('Profile strength', function (): void {
    it('starts at zero with next steps, and reaches 100 when everything is filled in', function (): void {
        $user = User::factory()->create();
        $profile = $user->careerProfile();
        expect($profile->completeness()['score'])->toBe(0)
            ->and($profile->completeness()['next'])->toHaveCount(9);

        $profile->update([
            'headline' => 'Backend developer',
            'summary' => str_repeat('I build reliable backends for real businesses. ', 3),
            'city' => 'Hamburg',
            'phone' => '+49 40 1234567',
        ]);
        $profile->experiences()->create(['title' => 'Developer', 'employer' => 'Nordlicht', 'starts_on' => '2021-03-01', 'achievements' => ['Shipped things']]);
        $profile->educations()->create(['institution' => 'Universität Hamburg', 'degree' => 'BSc', 'starts_on' => '2016-10-01']);
        foreach (['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker'] as $skill) {
            $profile->skills()->create(['name' => $skill, 'level' => 3]);
        }
        $profile->languages()->create(['name' => 'German', 'level' => 'C1']);
        $profile->links()->create(['label' => 'GitHub', 'url' => 'https://github.com/example']);

        expect($profile->completeness())->toBe(['score' => 100, 'next' => []]);
    });

    it('asks for an achievement on every job', function (): void {
        $profile = User::factory()->create()->careerProfile();
        $profile->experiences()->create(['title' => 'Developer', 'employer' => 'Nordlicht', 'starts_on' => '2021-03-01']);

        expect(collect($profile->completeness()['next'])->pluck('text'))
            ->toContain('List at least one achievement for every job.');
    });

    it('updates the score when a section changes', function (): void {
        $user = User::factory()->create();
        $panel = Livewire::actingAs($user)->test(Completeness::class)->assertSee('0%');

        $user->careerProfile()->update(['headline' => 'Backend developer']);
        $panel->dispatch('profile-changed')->assertSee('10%');
    });
});
