<?php

declare(strict_types=1);

use App\Livewire\Resumes\Compare;
use App\Livewire\Resumes\Editor;
use App\Livewire\Resumes\Index;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Resumes\AtsCheck;
use App\Resumes\DocxWriter;
use App\Resumes\ResumeDocument;
use App\Support\TextDiff;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** What Gotenberg would send back: a PDF with the candidate's CV text, without the skill "Figma". */
function fakeGotenberg(array $lines = ['Lena Brandt', 'Product designer', '+49 40 7654 321', 'Profile', 'I design tools.', 'Work experience', 'Senior product designer', 'Hafenblick Studio', 'Education', 'HAW Hamburg', 'Bachelor of Arts', 'Skills', 'User research', 'Languages', 'German']): void
{
    Http::fake(['gotenberg:3000/*' => Http::response(pdfWith($lines), 200, ['Content-Type' => 'application/pdf'])]);
}

/** The text of a Word file. */
function wordText(string $docx): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1);
}

beforeEach(fn () => Storage::fake('local'));

describe('Saving a version', function (): void {
    it('keeps the PDF and Word file, and lists what did not read back as text', function (): void {
        fakeGotenberg();
        $user = candidate();
        $user->careerProfile()->update(['email' => '']);
        $resume = resumeFor($user);

        Livewire::actingAs($user)->test(Editor::class, ['resumeId' => $resume->id])
            ->call('saveVersion')
            ->assertHasNoErrors()
            ->assertSee('Version 1')
            ->assertSee('Figma');

        $version = $resume->versions()->sole();
        expect($version->missing)->toBe(['Figma'])
            ->and($version->checked)->toBe(14)
            ->and($version->pages)->toBe(1)
            ->and($version->text)->toContain('Hafenblick Studio');
        Storage::disk('local')->assertExists([$version->pdf_path, $version->docx_path]);
        expect(wordText((string) Storage::disk('local')->get($version->docx_path)))->toContain('Senior product designer')->toContain('Raised task completion by 25%');

        // The page goes to Gotenberg with the font files it needs, and nothing from the dev server.
        Http::assertSent(function (Request $request): bool {
            $files = collect($request->data())->pluck('filename')->filter()->values()->all();
            $page = (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];

            return str_ends_with($request->url(), '/forms/chromium/convert/html')
                && $files === ['index.html', 'source-serif-4-400.woff2', 'source-serif-4-600.woff2', 'source-serif-4-700.woff2']
                && str_contains($page, "url('source-serif-4-400.woff2')")
                && ! str_contains($page, '5173')
                && ! str_contains($page, 'resume-pages');
        });
    });

    it('numbers the versions and says so when the PDF service is down', function (): void {
        $user = candidate();
        $resume = resumeFor($user);
        $editor = Livewire::actingAs($user)->test(Editor::class, ['resumeId' => $resume->id]);

        fakeGotenberg();
        $editor->call('saveVersion')->call('saveVersion');
        expect($resume->versions()->pluck('number')->all())->toBe([2, 1]);

        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $editor->call('saveVersion')->assertHasErrors('version');
        expect($resume->versions()->count())->toBe(2);
    });

    it('writes the Word file in the CV\'s language', function (): void {
        $user = candidate();
        $user->careerProfile()->experiences()->update(['starts_on' => '2021-03-01']);

        $text = wordText((new DocxWriter)->write(ResumeDocument::for(resumeFor($user, 'lebenslauf', 'de'))));

        expect($text)->toContain('Persönliche Daten')
            ->toContain('Berufserfahrung')
            ->toContain('03/2021 – heute')
            ->toContain('Muttersprache');
    });
});

describe('Versions', function (): void {
    it('download only for their owner, under a clear file name', function (): void {
        fakeGotenberg();
        $user = candidate();
        $version = ResumeVersion::saveFrom(resumeFor($user, 'lebenslauf', 'de'));

        $this->actingAs($user)->get("/resume-versions/{$version->id}/pdf")
            ->assertOk()
            ->assertDownload('lena-brandt-Lebenslauf-v1.pdf');
        $this->actingAs($user)->get("/resume-versions/{$version->id}/docx")->assertDownload('lena-brandt-Lebenslauf-v1.docx');
        $this->actingAs($user)->get("/resume-versions/{$version->id}/exe")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/resume-versions/{$version->id}/pdf")->assertNotFound();
    });

    it('go with their CV when it is deleted, while a duplicate starts without any', function (): void {
        fakeGotenberg();
        $user = candidate();
        $resume = resumeFor($user, settings: ['accent' => '#166534']);
        $version = ResumeVersion::saveFrom($resume);

        Livewire::actingAs($user)->test(Index::class)->call('duplicate', $resume->id);
        $copy = $user->resumes()->where('id', '!=', $resume->id)->sole();
        expect($copy->name)->toBe('Design roles (copy)')
            ->and($copy->look()['accent'])->toBe('#166534')
            ->and($copy->versions()->count())->toBe(0);

        Livewire::actingAs($user)->test(Index::class)->call('delete', $resume->id);
        Storage::disk('local')->assertMissing([$version->pdf_path, $version->docx_path]);
        expect(ResumeVersion::query()->count())->toBe(0);
    });

    it('compare line by line, among the person\'s own versions only', function (): void {
        $user = candidate();
        $resume = resumeFor($user);
        Http::fakeSequence('gotenberg:3000/*')
            ->push(pdfWith(['Lena Brandt', 'Skills', 'Figma']))
            ->push(pdfWith(['Lena Brandt', 'Skills', 'Figma', 'User research']));
        $old = ResumeVersion::saveFrom($resume);
        $new = ResumeVersion::saveFrom($resume);

        Livewire::withQueryParams(['a' => $old->id, 'b' => $new->id])->actingAs($user)->test(Compare::class)
            ->assertSee('One line changed')
            ->assertSee('User research');

        Livewire::withQueryParams(['a' => $old->id, 'b' => $new->id])->actingAs(User::factory()->create())->test(Compare::class)
            ->assertDontSee('User research');
    });
});

it('flags words a parser would read run together', function (): void {
    $user = candidate();
    $user->careerProfile()->update(['email' => '', 'phone' => '']);
    $resume = resumeFor($user, 'lebenslauf');
    $pdf = pdfWith(['Lena Brandt', 'Profile', 'Work experience', '03/2021 – Present Hafenblick Studio', '10/2017 – 08/2019Senior product designer', 'Education', 'HAW Hamburg Bachelor of Arts', 'Skills', 'Figma, User research', 'Languages', 'German']);

    expect(AtsCheck::run($pdf, ResumeDocument::for($resume))['missing'])->toBe(['Senior product designer']);
});

it('diffs lines, ignoring spacing', function (): void {
    expect(TextDiff::lines("Lena\nFigma\nHamburg", "Lena\n  Figma \nUser research\n\nBerlin"))->toBe([
        ['type' => 'same', 'text' => 'Lena'],
        ['type' => 'same', 'text' => 'Figma'],
        ['type' => 'removed', 'text' => 'Hamburg'],
        ['type' => 'added', 'text' => 'User research'],
        ['type' => 'added', 'text' => 'Berlin'],
    ]);
});
