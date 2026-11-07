<?php

declare(strict_types=1);

namespace App\Resumes;

use App\Livewire\Profile\Languages;
use App\Livewire\Profile\Skills;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\ProfileLanguage;
use App\Models\ProfileLink;
use App\Models\Project;
use App\Models\Resume;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * The CV as a Word document, for employers and portals that ask for one. Same content,
 * order and language as the PDF, in a plain layout that Word and parsers both handle.
 */
final class DocxWriter
{
    private string $accent;

    private Section $page;

    /** @return string The .docx file. */
    public function write(ResumeDocument $doc): string
    {
        return $doc->inLanguage(function () use ($doc): string {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            // Word can't take the CV's web fonts along, so it uses the nearest font every computer has.
            $word->setDefaultFontName(str_contains(Resume::FONTS[$doc->look['font']], 'serif') && ! str_contains(Resume::FONTS[$doc->look['font']], 'sans-serif') ? 'Georgia' : 'Arial');
            // Word takes whole points here; the PDF uses 9.5 and 10.5.
            $word->setDefaultFontSize($doc->look['pages'] === 1 ? 10 : 11);
            $word->getDocInfo()->setTitle($doc->title())->setCreator($doc->name());
            $this->accent = ltrim($doc->look['accent'], '#');
            $this->page = $word->addSection([
                'pageSizeW' => Converter::cmToTwip(21),
                'pageSizeH' => Converter::cmToTwip(29.7),
                'marginTop' => Converter::cmToTwip(1.6),
                'marginBottom' => Converter::cmToTwip(1.6),
                'marginLeft' => Converter::cmToTwip(1.8),
                'marginRight' => Converter::cmToTwip(1.8),
            ]);

            $doc->resume->template === 'lebenslauf' ? $this->lebenslauf($doc) : $this->singleColumn($doc);

            $file = (string) tempnam(sys_get_temp_dir(), 'cv');
            IOFactory::createWriter($word, 'Word2007')->save($file);
            $content = (string) file_get_contents($file);
            unlink($file);

            return $content;
        });
    }

    private function singleColumn(ResumeDocument $doc): void
    {
        $this->photo($doc);
        $this->page->addText($doc->name(), ['size' => 22, 'bold' => true, 'color' => $this->accent], ['spaceAfter' => 0]);
        if ($doc->profile->headline !== '') {
            $this->page->addText($doc->profile->headline, ['size' => 12]);
        }
        $this->page->addText(implode(' · ', $doc->contact()), ['color' => '57534E']);

        foreach ($doc->sections() as $section) {
            $this->heading($section['title']);
            $content = $section['content'];
            if (is_string($content)) {
                $this->paragraphs($content);

                continue;
            }
            match ($section['key']) {
                'skills' => $this->page->addText($content->pluck('name')->implode(', ')),
                'languages' => $this->page->addText($content->map(fn (Model $language): string => $language instanceof ProfileLanguage ? $language->name.' ('.($language->level === 'native' ? __('Native speaker') : $language->level).')' : '')->implode(', ')),
                default => $this->entries($doc, $content),
            };
        }
        $this->signature($doc);
    }

    private function lebenslauf(ResumeDocument $doc): void
    {
        $this->photo($doc);
        $this->page->addText(mb_strtoupper(__('Curriculum vitae')), ['color' => $this->accent, 'size' => 9]);
        $this->page->addText($doc->name(), ['size' => 22, 'bold' => true], ['spaceAfter' => 0]);
        if ($doc->profile->headline !== '') {
            $this->page->addText($doc->profile->headline, ['size' => 12]);
        }

        $this->heading(__('Personal details'));
        $table = $this->table();
        foreach (array_filter([__('Place of residence') => $doc->place(), __('Phone') => $doc->profile->phone, __('Email') => $doc->profile->email]) as $label => $value) {
            $this->row($table, $label, [$value]);
        }

        foreach ($doc->sections() as $section) {
            $this->heading($section['title']);
            $content = $section['content'];
            if (is_string($content)) {
                $this->paragraphs($content);

                continue;
            }
            $table = $this->table();
            if ($section['key'] === 'skills') {
                foreach ($content->sortByDesc('level')->groupBy('level') as $level => $skills) {
                    $this->row($table, Skills::levels()[$level] ?? '', [$skills->pluck('name')->implode(', ')]);
                }

                continue;
            }
            foreach ($content as $entry) {
                match (true) {
                    $entry instanceof Experience => $this->row($table, $doc->period($entry->starts_on, $entry->ends_on), [$entry->title, implode(', ', array_filter([$entry->employer, $entry->city])), (string) $entry->description], $entry->achievements),
                    $entry instanceof Education => $this->row($table, $doc->period($entry->starts_on, $entry->ends_on), [implode(', ', array_filter([$entry->degree, $entry->field])), implode(', ', array_filter([$entry->institution, $entry->city])), $entry->grade !== '' ? __('Grade: :grade', ['grade' => $entry->grade]) : '']),
                    $entry instanceof ProfileLanguage => $this->row($table, $entry->name, [Languages::levels()[$entry->level] ?? $entry->level]),
                    $entry instanceof Project => $this->row($table, $doc->period($entry->starts_on, $entry->ends_on, ongoing: false), [implode(', ', array_filter([$entry->name, $entry->role])), (string) $entry->description, $entry->url]),
                    $entry instanceof Certification => $this->row($table, $doc->month($entry->issued_on), [implode(', ', array_filter([$entry->name, $entry->issuer]))]),
                    $entry instanceof ProfileLink => $this->row($table, $entry->label, [$entry->url]),
                    default => null,
                };
            }
        }
        $this->signature($doc);
    }

    /** @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $entries */
    private function entries(ResumeDocument $doc, Collection $entries): void
    {
        foreach ($entries as $entry) {
            [$title, $details, $text, $bullets] = match (true) {
                $entry instanceof Experience => [$entry->title, [implode(', ', array_filter([$entry->employer, $entry->city])), $doc->period($entry->starts_on, $entry->ends_on)], (string) $entry->description, $entry->achievements],
                $entry instanceof Education => [implode(', ', array_filter([$entry->degree, $entry->field])), [implode(', ', array_filter([$entry->institution, $entry->city])), $doc->period($entry->starts_on, $entry->ends_on), $entry->grade !== '' ? __('Grade: :grade', ['grade' => $entry->grade]) : ''], (string) $entry->description, []],
                $entry instanceof Project => [implode(', ', array_filter([$entry->name, $entry->role])), [$doc->period($entry->starts_on, $entry->ends_on, ongoing: false), $entry->url], (string) $entry->description, $entry->achievements],
                $entry instanceof Certification => [$entry->name, [$entry->issuer, $doc->month($entry->issued_on)], '', []],
                $entry instanceof ProfileLink => [$entry->label, [$entry->url], '', []],
                default => ['', [], '', []],
            };
            $this->page->addText($title, ['bold' => true], ['spaceBefore' => 120, 'spaceAfter' => 0, 'keepNext' => true]);
            $line = implode(' · ', array_filter($details));
            if ($line !== '') {
                $this->page->addText($line, ['color' => '57534E'], ['spaceAfter' => 40]);
            }
            if ($text !== '') {
                $this->paragraphs($text);
            }
            foreach ($bullets as $bullet) {
                $this->page->addListItem($bullet);
            }
        }
    }

    private function heading(string $title): void
    {
        $this->page->addText($title, ['bold' => true, 'size' => 12, 'color' => $this->accent], [
            'spaceBefore' => 240,
            'spaceAfter' => 80,
            'keepNext' => true,
            'borderBottomSize' => 6,
            'borderBottomColor' => $this->accent,
        ]);
    }

    private function paragraphs(string $text): void
    {
        foreach (preg_split('/\R+/', $text) ?: [] as $paragraph) {
            $this->page->addText($paragraph);
        }
    }

    private function table(): Table
    {
        return $this->page->addTable(['cellMargin' => 40]);
    }

    /**
     * A row of the Lebenslauf: a label or dates on the left, lines and bullets on the right.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $bullets
     */
    private function row(Table $table, string $label, array $lines, array $bullets = []): void
    {
        $table->addRow(null, ['cantSplit' => true]);
        $table->addCell((int) Converter::cmToTwip(3.6))->addText($label, ['color' => '57534E']);
        $cell = $table->addCell((int) Converter::cmToTwip(13.8));
        // A title over its details is bold; a single value, such as a phone number, isn't.
        $lines = array_values(array_filter($lines));
        foreach ($lines as $index => $line) {
            $cell->addText($line, $index === 0 && count($lines) > 1 ? ['bold' => true] : []);
        }
        foreach ($bullets as $bullet) {
            $cell->addListItem($bullet);
        }
    }

    private function photo(ResumeDocument $doc): void
    {
        $photo = $doc->photo();
        if ($photo !== null) {
            $this->page->addImage((string) base64_decode(substr($photo, strlen('data:image/jpeg;base64,'))), [
                'width' => Converter::cmToPoint(3),
                'height' => Converter::cmToPoint(4),
                'positioning' => 'absolute',
                'posHorizontal' => 'right',
                'posHorizontalRel' => 'margin',
                'posVerticalRel' => 'margin',
                'wrappingStyle' => 'square',
            ]);
        }
    }

    private function signature(ResumeDocument $doc): void
    {
        if ($doc->look['signature'] && $doc->resume->template === 'lebenslauf') {
            $this->page->addText($doc->signedAt(), [], ['spaceBefore' => 480]);
            $this->page->addText($doc->name(), [], ['spaceBefore' => 600, 'borderTopSize' => 6, 'borderTopColor' => '1C1917', 'indentation' => ['right' => Converter::cmToTwip(10.5)]]);
        }
    }
}
