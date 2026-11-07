<?php

declare(strict_types=1);

use App\Livewire\Profile\Photo;
use App\Livewire\Resumes\Editor;
use App\Livewire\Resumes\Index;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A fictional person with a filled-in profile. */
function candidate(): User
{
    $user = User::factory()->create(['name' => 'Lena Brandt']);
    $profile = $user->careerProfile();
    $profile->update(['headline' => 'Product designer', 'summary' => 'I design tools that people in logistics use every day.', 'city' => 'Hamburg', 'country' => 'DE', 'phone' => '+49 40 7654321']);
    $profile->experiences()->create(['title' => 'Senior product designer', 'employer' => 'Hafenblick Studio', 'city' => 'Hamburg', 'starts_on' => '2021-03-01', 'achievements' => ['Raised task completion by 25%'], 'position' => 1]);
    $profile->educations()->create(['institution' => 'HAW Hamburg', 'degree' => 'Bachelor of Arts', 'field' => 'Communication design', 'starts_on' => '2014-10-01', 'ends_on' => '2018-07-01', 'grade' => '1.7', 'position' => 1]);
    $profile->skills()->create(['name' => 'Figma', 'level' => 4, 'position' => 1]);
    $profile->skills()->create(['name' => 'User research', 'level' => 3, 'position' => 2]);
    $profile->languages()->create(['name' => 'German', 'level' => 'native', 'position' => 1]);

    return $user;
}

function resumeFor(User $user, string $template = 'classic', string $language = 'en', array $settings = []): Resume
{
    return $user->resumes()->create([
        'name' => 'Design roles',
        'language' => $language,
        'template' => $template,
        'settings' => [...Resume::defaults($template), ...$settings],
    ]);
}

describe('CV pages', function (): void {
    it('are for signed-in people, and each CV only for its owner', function (): void {
        $resume = resumeFor(candidate());
        $this->get('/resumes')->assertRedirect('/login');

        $someoneElse = User::factory()->create();
        $this->actingAs($someoneElse)->get("/resumes/{$resume->id}")->assertNotFound();
        $this->actingAs($someoneElse)->get("/resumes/{$resume->id}/preview")->assertNotFound();
        Livewire::actingAs($someoneElse)->test(Editor::class, ['resumeId' => $resume->id])->assertNotFound();
        Livewire::actingAs($someoneElse)->test(Index::class)->call('delete', $resume->id)->assertNotFound();

        $this->actingAs($resume->user)->get("/resumes/{$resume->id}")->assertOk()->assertSee('Design roles');
    });

    it('makes a new CV from a template, with that template\'s defaults', function (): void {
        $user = candidate();

        Livewire::actingAs($user)->test(Index::class)
            ->set('name', 'Design roles, German')
            ->set('language', 'de')
            ->set('template', 'lebenslauf')
            ->call('create')
            ->assertRedirect(route('resumes.edit', ['id' => $user->resumes()->sole()->id]));

        expect($user->resumes()->sole()->look())->toMatchArray(['font' => 'ibm-plex-sans', 'photo' => true, 'signature' => true, 'pages' => 2]);
    });

    it('lists the CVs in German too, and deletes one', function (): void {
        $resume = resumeFor(candidate());
        $this->actingAs(User::factory()->create(['locale' => 'de']))->get('/resumes')->assertOk()->assertSee('Neuer Lebenslauf');

        Livewire::actingAs($resume->user)->test(Index::class)->assertSee('Design roles')->call('delete', $resume->id);
        expect(Resume::query()->count())->toBe(0);
    });
});

describe('The CV itself', function (): void {
    it('comes in English and German from the same profile', function (): void {
        $user = candidate();
        app()->setLocale('en');

        $this->actingAs($user)->get('/resumes/'.resumeFor($user)->id.'/preview')
            ->assertOk()
            ->assertSee('Work experience')
            ->assertSee('Mar 2021 – Present')
            ->assertSee('German (Native speaker)');

        $this->actingAs($user)->get('/resumes/'.resumeFor($user, 'lebenslauf', 'de')->id.'/preview')
            ->assertSee('Lebenslauf')
            ->assertSee('Persönliche Daten')
            ->assertSee('Berufserfahrung')
            ->assertSee('03/2021 – heute')
            ->assertSee('Note: 1.7')
            ->assertSee('Muttersprache');

        // Rendering in German leaves the visitor's own language alone.
        expect(App::getLocale())->toBe('en');
    });

    it('shows the sections in the chosen order, without hidden or empty ones', function (): void {
        $user = candidate();
        $editor = Livewire::actingAs($user)->test(Editor::class, ['resumeId' => resumeFor($user)->id])
            ->call('move', 'skills', -1)
            ->call('move', 'skills', -1)
            ->call('toggle', 'educations');

        $html = $this->actingAs($user)->get('/resumes/'.$editor->get('resumeId').'/preview')->getContent();
        expect(strpos($html, '<h2>Skills</h2>'))->toBeLessThan(strpos($html, '<h2>Work experience</h2>'))
            ->and($html)->not->toContain('HAW Hamburg')
            ->and($html)->not->toContain('<h2>Projects</h2>');
    });

    it('puts the photo and the signature line on a Lebenslauf only when chosen', function (): void {
        Storage::fake('local');
        $user = candidate();
        Livewire::actingAs($user)->test(Photo::class)->set('photo', UploadedFile::fake()->image('me.jpg', 600, 800))->call('save');
        $resume = resumeFor($user, 'lebenslauf', 'de');

        $this->actingAs($user)->get("/resumes/{$resume->id}/preview")
            ->assertSee('src="data:image/jpeg;base64,', escape: false)
            ->assertSee('Hamburg, '.now()->locale('de')->translatedFormat('j. F Y'));

        $resume->update(['settings' => [...$resume->settings, 'photo' => false, 'signature' => false]]);
        $this->actingAs($user)->get("/resumes/{$resume->id}/preview")
            ->assertDontSee('data:image/jpeg', escape: false)
            ->assertDontSee('class="signature-line"', escape: false);
    });

    it('renders every template with the profile in reading order', function (string $template): void {
        $user = candidate();
        $text = strip_tags($this->actingAs($user)->get('/resumes/'.resumeFor($user, $template)->id.'/preview')->getContent());

        expect($text)->toContain('Lena Brandt')
            ->and(strpos($text, 'Senior product designer'))->toBeLessThan(strpos($text, 'Raised task completion by 25%'))
            ->and($text)->toContain('Figma');
    })->with(array_keys(Resume::TEMPLATES));
});

describe('The editor', function (): void {
    it('saves each change, and a new template brings its own font and colour', function (): void {
        $user = candidate();
        $resume = resumeFor($user);

        Livewire::actingAs($user)->test(Editor::class, ['resumeId' => $resume->id])
            ->set('accent', '#166534')
            ->set('pages', '1')
            ->assertDispatched('resume-saved')
            ->set('template', 'modern')
            ->assertSet('font', 'instrument-sans')
            ->assertSet('accent', '#0f766e')
            ->set('template', 'lebenslauf')
            ->assertSet('photo', true)
            ->assertSet('signature', true)
            ->set('template', 'modern')
            ->assertSet('signature', false);

        expect($resume->fresh()->look())->toMatchArray(['font' => 'instrument-sans', 'accent' => '#0f766e', 'pages' => 1])
            ->and($resume->fresh()->template)->toBe('modern');
    });

    it('keeps saving the other settings while one field is invalid', function (): void {
        $user = candidate();
        $resume = resumeFor($user);

        Livewire::actingAs($user)->test(Editor::class, ['resumeId' => $resume->id])
            ->set('name', '')
            ->assertHasErrors('name')
            ->set('accent', '#166534')
            ->call('toggle', 'skills')
            ->assertHasErrors('name')
            ->assertDispatched('resume-saved');

        $resume->refresh();
        expect($resume->name)->toBe('Design roles')
            ->and($resume->look()['accent'])->toBe('#166534')
            ->and($resume->look()['hidden'])->toBe(['skills']);
    });

    it('only takes the fonts, colours and sections it offers', function (): void {
        $user = candidate();
        $resume = resumeFor($user);

        Livewire::actingAs($user)->test(Editor::class, ['resumeId' => $resume->id])
            ->set('accent', '#ffff00')->assertHasErrors('accent')
            ->set('font', 'Comic Sans')->assertHasErrors('font')
            ->set('hidden', ['passwords'])->assertHasErrors('hidden.0');

        expect($resume->fresh()->look()['accent'])->toBe('#1f2937');
    });
});
