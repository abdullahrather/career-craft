<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

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
