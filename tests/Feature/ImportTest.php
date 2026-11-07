<?php

declare(strict_types=1);

use App\Ai\Agents\CvReader;
use App\Import\CvText;
use App\Import\ProfileDraft;
use App\Import\UnreadableFile;
use App\Jobs\ReadCv;
use App\Livewire\Profile\Import;
use App\Models\ProfileImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/** A Word file with one paragraph per line. */
function docxWith(array $lines): string
{
    $paragraphs = implode('', array_map(fn (string $line): string => '<w:p><w:r><w:t>'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>', $lines));

    return zipWith([
        '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
        'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>',
    ]);
}

function cvLines(): array
{
    return [
        'Jonas Weber',
        'Backend developer, PHP and Laravel',
        'jonas.weber@example.com, +49 40 1234567, Hamburg',
        'Senior backend developer, Nordlicht Logistik, since 03/2021',
        'Cut order processing time by 40 percent.',
        'Led the move of four services to PostgreSQL.',
    ];
}

/** What the AI might answer for cvLines(). */
function aiAnswer(array $overrides = []): array
{
    return [
        'personal' => ['headline' => 'Backend developer, PHP and Laravel', 'summary' => '', 'email' => 'jonas.weber@example.com', 'phone' => '+49 40 1234567', 'city' => 'Hamburg', 'country' => 'DE'],
        'experiences' => [[
            'title' => 'Senior backend developer', 'employer' => 'Nordlicht Logistik', 'city' => 'Hamburg', 'country' => 'DE',
            'starts_on' => '2021-03', 'ends_on' => '', 'description' => '',
            'achievements' => ['Cut order processing time by 40 percent.', 'Led the move of four services to PostgreSQL.'],
        ]],
        'educations' => [],
        'skills' => [['name' => 'PHP'], ['name' => 'Laravel'], ['name' => 'PostgreSQL']],
        'languages' => [['name' => 'German', 'level' => 'native'], ['name' => 'English', 'level' => 'C1']],
        'projects' => [],
        'certifications' => [],
        'links' => [['label' => '', 'url' => 'github.com/jonas-weber-example']],
        ...$overrides,
    ];
}

function csvOf(array $rows): string
{
    $stream = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($stream, $row, escape: '');
    }
    rewind($stream);

    return (string) stream_get_contents($stream);
}

function linkedInExport(): string
{
    return zipWith([
        'Profile.csv' => csvOf([
            ['First Name', 'Last Name', 'Maiden Name', 'Address', 'Birth Date', 'Headline', 'Summary', 'Industry', 'Zip Code', 'Geo Location', 'Twitter Handles', 'Websites', 'Instant Messengers'],
            ['Lena', 'Brandt', '', '', '', 'Product designer', 'I design tools that people in logistics use every day.', 'Design', '', 'Hamburg, Germany', '', '[PORTFOLIO:https://lena-brandt.example.com]', ''],
        ]),
        'Positions.csv' => csvOf([
            ['Company Name', 'Title', 'Description', 'Location', 'Started On', 'Finished On'],
            ['Hafenblick Studio', 'Product designer', "Design system for three apps.\n• Raised task completion by 25%\n• Ran 40 user interviews", 'Hamburg, Germany', 'Mar 2021', ''],
            ['Elbwerk', 'UX designer', '', 'Bremen, Germany', 'Sep 2018', 'Feb 2021'],
        ]),
        'Education.csv' => csvOf([
            ['School Name', 'Start Date', 'End Date', 'Notes', 'Degree Name', 'Activities'],
            ['HAW Hamburg', '2014', '2018', '', 'Bachelor of Arts', ''],
        ]),
        'Skills.csv' => csvOf([['Name'], ['Figma'], ['User research'], ['figma']]),
        'Languages.csv' => csvOf([['Name', 'Proficiency'], ['German', 'Native or bilingual proficiency'], ['English', 'Full professional proficiency'], ['Danish', '']]),
        'Email Addresses.csv' => csvOf([
            ['Email Address', 'Confirmed', 'Primary', 'Updated On'],
            ['old@example.com', 'Yes', 'No', ''],
            ['lena.brandt@example.com', 'Yes', 'Yes', ''],
        ]),
        'PhoneNumbers.csv' => csvOf([['Extension', 'Number', 'Type'], ['', '+49 40 7654321', 'Mobile']]),
    ]);
}

describe('Reading CV files', function (): void {
    it('reads the text of a PDF and a Word file', function (): void {
        $pdf = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($pdf, pdfWith(cvLines()));
        $docx = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($docx, docxWith(cvLines()));

        expect(CvText::from($pdf, 'pdf'))->toContain('Nordlicht Logistik')->toContain('Led the move of four services')
            ->and(CvText::from($docx, 'DOCX'))->toContain("Jonas Weber\nBackend developer");
    });

    it('refuses a file that is not what its name says', function (): void {
        $fake = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($fake, 'just some text');

        expect(fn () => CvText::from($fake, 'pdf'))->toThrow(UnreadableFile::class)
            ->and(fn () => CvText::from($fake, 'docx'))->toThrow(UnreadableFile::class);
    });
});

describe('Importing a CV', function (): void {
    it('has the AI read the CV, and saves only what the person ticks', function (): void {
        CvReader::fake([aiAnswer()]);
        $user = User::factory()->create();
        $user->careerProfile()->update(['city' => 'Berlin']);
        $user->careerProfile()->skills()->create(['name' => 'php', 'level' => 4]);

        $import = Livewire::actingAs($user)->test(Import::class)
            ->set('cv', UploadedFile::fake()->createWithContent('jonas-weber-cv.pdf', pdfWith(cvLines())))
            ->assertHasNoErrors()
            ->assertSee('Found in jonas-weber-cv.pdf')
            ->assertSee('Nordlicht Logistik')
            ->assertSee('Already in your profile')
            // Empty fields are ticked; one the person filled in themselves is not.
            ->assertSet('usePersonal.headline', true)
            ->assertSet('usePersonal.city', false)
            ->assertSet('use.skills', [false, true, true]);

        CvReader::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Nordlicht Logistik'));
        expect($user->careerProfile()->import->text)->toBeNull();

        $import->set('use.languages.1', false)->call('apply')->assertRedirect(route('profile'));

        $profile = $user->careerProfile()->fresh();
        expect($profile->headline)->toBe('Backend developer, PHP and Laravel')
            ->and($profile->city)->toBe('Berlin')
            ->and($profile->experiences()->sole()->achievements)->toHaveCount(2)
            ->and($profile->experiences()->sole()->ends_on)->toBeNull()
            ->and($profile->skills()->pluck('name')->all())->toBe(['php', 'Laravel', 'PostgreSQL'])
            ->and($profile->languages()->pluck('name')->all())->toBe(['German'])
            ->and($profile->links()->sole()->only('label', 'url'))->toBe(['label' => 'github.com', 'url' => 'https://github.com/jonas-weber-example'])
            ->and($profile->import)->toBeNull();
    });

    it('lets the person correct a field before it is saved, with the profile rules', function (): void {
        CvReader::fake([aiAnswer()]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Import::class)
            ->set('cv', UploadedFile::fake()->createWithContent('cv.docx', docxWith(cvLines())))
            ->set('personal.phone', 'call me')
            ->call('apply')
            ->assertHasErrors('personal.phone')
            ->set('personal.phone', '+49 40 999999')
            ->call('apply')
            ->assertHasNoErrors();

        expect($user->careerProfile()->fresh()->phone)->toBe('+49 40 999999');
    });

    it('adds entries when no personal detail is ticked', function (): void {
        CvReader::fake([aiAnswer()]);
        $user = User::factory()->create();

        $import = Livewire::actingAs($user)->test(Import::class)
            ->set('cv', UploadedFile::fake()->createWithContent('cv.docx', docxWith(cvLines())));
        foreach (array_keys($import->get('usePersonal')) as $field) {
            $import->set("usePersonal.{$field}", false);
        }
        $import->call('apply')->assertRedirect(route('profile'));

        expect($user->careerProfile()->fresh()->headline)->toBe('')
            ->and($user->careerProfile()->skills()->count())->toBe(3);
    });

    it('only saves the personal fields the form has, whatever the browser sends', function (): void {
        CvReader::fake([aiAnswer()]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Import::class)
            ->set('cv', UploadedFile::fake()->createWithContent('cv.docx', docxWith(cvLines())))
            ->set('personal.photo_path', 'photos/1-someone-else.jpg')
            ->set('usePersonal.photo_path', true)
            ->call('apply');

        expect($user->careerProfile()->fresh()->photo_path)->toBeNull();
    });

    it('asks for a text file instead of sending a scan to the AI', function (): void {
        CvReader::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Import::class)
            ->set('cv', UploadedFile::fake()->createWithContent('scan.pdf', pdfWith(['Jonas'])))
            ->assertHasErrors('cv')
            ->set('cv', UploadedFile::fake()->createWithContent('cv.txt', str_repeat('text ', 50)))
            ->assertHasErrors('cv');

        CvReader::assertNeverPrompted();
        expect($user->careerProfile()->import)->toBeNull();
    });

    it('drops what the profile would refuse, and says what was left out', function (): void {
        $draft = ProfileDraft::normalise(aiAnswer([
            'personal' => ['headline' => str_repeat('Developer ', 30), 'email' => 'not an email', 'country' => 'Germany', 'city' => "  Ham\nburg "],
            'experiences' => [
                ['title' => 'Developer', 'employer' => 'Nordlicht', 'starts_on' => '2019', 'ends_on' => '2021-02', 'description' => 'Shipped things.', 'achievements' => ['• Shipped things', '']],
                ['title' => 'Designer', 'employer' => 'Elbwerk', 'starts_on' => '2017-02', 'ends_on' => '2018-12', 'description' => 'Design system. Shipped things.', 'achievements' => ['Shipped things']],
                ['title' => 'Developer', 'employer' => 'Nordlicht', 'starts_on' => '2019', 'ends_on' => '2021-02'],
                ['title' => 'Intern', 'employer' => 'Elbwerk', 'starts_on' => ''],
                ['title' => 'Trainee', 'employer' => 'Hafenblick', 'starts_on' => '2020-05', 'ends_on' => '2019-01'],
                ['title' => '', 'employer' => ''],
            ],
            'languages' => [['name' => 'Danish', 'level' => 'fluent'], ['name' => 'French', 'level' => 'b1']],
            'links' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']],
        ]));

        expect($draft['personal'])->toMatchArray(['email' => '', 'country' => 'DE', 'city' => 'Ham burg'])
            ->and(mb_strlen($draft['personal']['headline']))->toBe(120)
            ->and($draft['entries']['experiences'])->toHaveCount(2)
            // A description that only repeats the bullet points goes; one with more stays.
            ->and($draft['entries']['experiences'][0])->toMatchArray(['starts_on' => '2019-01', 'description' => '', 'achievements' => ['Shipped things']])
            ->and($draft['entries']['experiences'][1]['description'])->toBe('Design system. Shipped things.')
            ->and($draft['entries']['languages'])->toBe([['name' => 'French', 'level' => 'B1']])
            ->and($draft['entries']['links'])->toBe([])
            ->and($draft['skipped'])->toBe([
                ['name' => 'Intern, Elbwerk', 'reason' => ProfileDraft::NO_START],
                ['name' => 'Trainee, Hafenblick', 'reason' => ProfileDraft::BAD_DATES],
                ['name' => 'Danish', 'reason' => ProfileDraft::NO_LEVEL],
            ]);
    });

    it('marks the import as failed when the AI gives up, and forgets the text', function (): void {
        $import = User::factory()->create()->careerProfile()->import()->create(['source' => 'cv', 'filename' => 'cv.pdf', 'text' => 'Jonas Weber']);

        (new ReadCv($import))->failed(new RuntimeException('Timed out'));

        expect($import->fresh())->status->toBe(ProfileImport::FAILED)->text->toBeNull();
    });
});

describe('Importing from LinkedIn', function (): void {
    it('reads a LinkedIn data export', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Import::class)
            ->set('linkedin', UploadedFile::fake()->createWithContent('Basic_LinkedInDataExport.zip', linkedInExport()))
            ->assertHasNoErrors()
            ->assertSee('Hafenblick Studio')
            ->assertSee('Danish has no level')
            ->call('apply');

        $profile = $user->careerProfile()->fresh();
        $job = $profile->experiences()->first();
        expect($profile->only('headline', 'email', 'phone', 'city', 'country'))->toBe([
            // The profile already had the account's email, so LinkedIn's was not ticked.
            'headline' => 'Product designer', 'email' => $user->email, 'phone' => '+49 40 7654321', 'city' => 'Hamburg', 'country' => 'DE',
        ])
            ->and($job->starts_on->format('Y-m'))->toBe('2021-03')
            ->and($job->ends_on)->toBeNull()
            ->and($job->description)->toBe('Design system for three apps.')
            ->and($job->achievements)->toBe(['Raised task completion by 25%', 'Ran 40 user interviews'])
            ->and($profile->experiences()->count())->toBe(2)
            ->and($profile->educations()->sole()->starts_on->format('Y-m'))->toBe('2014-01')
            ->and($profile->skills()->pluck('name')->all())->toBe(['Figma', 'User research'])
            ->and($profile->languages()->pluck('level', 'name')->all())->toBe(['German' => 'native', 'English' => 'C1'])
            ->and($profile->links()->sole()->url)->toBe('https://lena-brandt.example.com');
    });

    it('says so when the ZIP is not a LinkedIn export', function (): void {
        Livewire::actingAs(User::factory()->create())->test(Import::class)
            ->set('linkedin', UploadedFile::fake()->createWithContent('photos.zip', zipWith(['holiday.txt' => 'sun'])))
            ->assertHasErrors('linkedin');
    });
});

describe('The import page', function (): void {
    it('is in German too', function (): void {
        $this->actingAs(User::factory()->create(['locale' => 'de']))->get('/profile/import')
            ->assertOk()
            ->assertSee('Lebenslauf importieren');
    });

    it("keeps each person's import to themselves", function (): void {
        $owner = User::factory()->create();
        Livewire::actingAs($owner)->test(Import::class)
            ->set('linkedin', UploadedFile::fake()->createWithContent('export.zip', linkedInExport()));

        Livewire::actingAs(User::factory()->create())->test(Import::class)
            ->assertDontSee('Hafenblick Studio')
            ->call('apply')
            ->assertNotFound();

        expect($owner->careerProfile()->import)->not->toBeNull();
    });
});
