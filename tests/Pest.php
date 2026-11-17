<?php

declare(strict_types=1);

use App\JobSources\FoundJob;
use App\JobSources\ListingStore;
use App\Models\AiUsage;
use App\Models\CoverLetter;
use App\Models\JobListing;
use App\Models\Resume;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Live');

/** Records AI tokens the person has used, today unless another time is given. */
function useToday(User $user, int $tokens, ?DateTimeInterface $at = null): void
{
    AiUsage::query()->create(['user_id' => $user->id, 'feature' => 'test', 'provider' => 'ollama', 'model' => 'qwen2.5:7b', 'input_tokens' => $tokens, 'output_tokens' => 0, 'cost' => 0])
        ->forceFill(['created_at' => $at ?? now()])->save();
}

/** A fictional job as a source would find it. */
function foundJob(array $values = []): FoundJob
{
    return new FoundJob(...[
        'source' => 'arbeitnow',
        'externalId' => 'backend-developer-kranich-1',
        'url' => 'https://www.arbeitnow.com/jobs/backend-developer-kranich-1',
        'title' => 'Backend Developer PHP',
        'company' => 'Kranich Mobility GmbH',
        'location' => 'Hamburg',
        'workMode' => '',
        'description' => 'We build booking software for ferries.',
        ...$values,
    ]);
}

/** A one-page PDF with these lines of text. */
function pdfWith(array $lines): string
{
    $text = implode(' Tj T* ', array_map(fn (string $line): string => '('.addcslashes($line, '()\\').')', $lines));
    $content = "BT /F1 11 Tf 14 TL 72 760 Td {$text} Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $number => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/** A ZIP file with these files in it. */
function zipWith(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
}

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

/** A fictional candidate: a PHP backend developer. */
function backendCandidate(): User
{
    $user = User::factory()->create();
    $profile = $user->careerProfile();
    $profile->update(['headline' => 'Backend Developer']);
    $profile->experiences()->create(['title' => 'Software Engineer', 'employer' => 'Nordlicht Logistik', 'city' => 'Hamburg', 'starts_on' => '2021-03-01']);
    foreach (['PHP', 'Laravel', 'PostgreSQL'] as $position => $skill) {
        $profile->skills()->create(['name' => $skill, 'position' => $position]);
    }

    return $user;
}

/** Saves fictional jobs, the first one newest. */
function saveJobs(array $jobs): void
{
    foreach (array_reverse($jobs) as $index => $values) {
        ListingStore::save([foundJob(['externalId' => "job-{$index}", 'company' => "Firma {$index}", ...$values])]);
        JobListing::query()->latest('id')->first()->update(['posted_at' => now()->subDays(count($jobs) - $index)]);
    }
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
