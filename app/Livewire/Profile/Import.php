<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Import\CvText;
use App\Import\LinkedInExport;
use App\Import\ProfileDraft;
use App\Import\UnreadableFile;
use App\Jobs\ReadCv;
use App\Models\Profile;
use App\Models\ProfileImport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Fills the profile from an old CV or a LinkedIn export. Nothing is saved until the
 * person has gone through what was found and ticked what they want.
 *
 * @phpstan-import-type Draft from ProfileDraft
 */
final class Import extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $cv = null;

    /** @var TemporaryUploadedFile|null */
    public $linkedin = null;

    /** @var array<string, string> Personal details found, as the person may edit them. */
    public array $personal = [];

    /** @var array<string, bool> Which personal details to use. */
    public array $usePersonal = [];

    /** @var array<string, array<int, bool>> Section => entry index => use it. */
    public array $use = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function updatedCv(): void
    {
        $this->validate(['cv' => ['required', 'file', 'extensions:pdf,docx', 'max:5120']], attributes: ['cv' => __('CV')]);
        $file = $this->upload($this->cv);

        try {
            $text = CvText::from($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (UnreadableFile) {
            $text = '';
        }
        $file->delete();
        $this->cv = null;
        // Under a few lines means a scan or a picture of a CV, which has no text to read.
        if (mb_strlen($text) < 100) {
            $this->addError('cv', __('We could not find any text in this file. If it is a scan or a picture, upload the Word file or a PDF exported from your CV tool instead.'));

            return;
        }

        $import = $this->start('cv', $file->getClientOriginalName(), ['status' => ProfileImport::READING, 'text' => $text]);
        ReadCv::dispatch($import);
        $this->refresh();
    }

    public function updatedLinkedin(): void
    {
        $this->validate(['linkedin' => ['required', 'file', 'extensions:zip', 'max:10240']], attributes: ['linkedin' => __('LinkedIn export')]);
        $file = $this->upload($this->linkedin);

        try {
            $draft = ProfileDraft::normalise(LinkedInExport::read($file->getRealPath()));
        } catch (UnreadableFile) {
            $draft = null;
        }
        $file->delete();
        $this->linkedin = null;
        if ($draft === null) {
            $this->addError('linkedin', __('This is not a LinkedIn data export. Download it from LinkedIn under Settings, Data privacy, Get a copy of your data.'));

            return;
        }

        $this->start('linkedin', $file->getClientOriginalName(), ['status' => ProfileImport::READY, 'data' => $draft]);
        $this->refresh();
    }

    /** Sets up the choices once the import is ready. Polled while the AI is reading. */
    public function refresh(): void
    {
        $this->personal = [];
        $this->usePersonal = [];
        $this->use = [];
        $profile = $this->profile();
        $draft = $this->draft();
        if ($draft === null) {
            return;
        }

        foreach ($draft['personal'] as $field => $value) {
            $current = $profile->getAttribute($field);
            $current = is_string($current) ? $current : '';
            if ($value !== '' && $value !== $current) {
                $this->personal[$field] = $value;
                // Don't replace something the person wrote themselves unless they say so.
                $this->usePersonal[$field] = $current === '';
            }
        }
        $existing = ProfileDraft::existing($profile);
        foreach ($draft['entries'] as $section => $entries) {
            foreach ($entries as $index => $entry) {
                $this->use[$section][$index] = ! in_array(ProfileDraft::key($section, $entry), $existing[$section], true);
            }
        }
    }

    public function apply(): void
    {
        $draft = $this->draft();
        abort_if($draft === null, 404);

        // The browser can add keys to these arrays, so only the profile's personal fields count.
        $chosen = array_intersect_key($this->personal, array_filter($this->usePersonal), PersonalDetails::rules());
        $rules = array_intersect_key(PersonalDetails::rules(), $chosen);
        // Livewire refuses to validate with no rules, which is the case when nothing personal is ticked.
        if ($rules !== []) {
            $this->validate(
                array_combine(array_map(fn (string $field): string => 'personal.'.$field, array_keys($rules)), $rules),
                attributes: array_combine(
                    array_map(fn (string $field): string => 'personal.'.$field, array_keys(PersonalDetails::labels())),
                    PersonalDetails::labels(),
                ),
            );
        }

        $profile = $this->profile();
        $count = ProfileDraft::apply($profile, $draft, array_map('trim', $chosen), $this->use);
        $profile->import()->delete();

        session()->flash('status', trans_choice('{0} Nothing was added to your profile.|{1} Added one item to your profile.|[2,*] Added :count items to your profile.', $count));
        $this->redirectRoute('profile');
    }

    public function discard(): void
    {
        $this->profile()->import()->delete();
        $this->refresh();
    }

    public function render(): View
    {
        $import = $this->profile()->import;

        return view('livewire.profile.import', [
            'import' => $import,
            'draft' => $this->draft(),
            'labels' => PersonalDetails::labels(),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function start(string $source, string $filename, array $attributes): ProfileImport
    {
        // One import at a time: a new file replaces one that wasn't finished.
        $profile = $this->profile();
        $profile->import()->delete();

        return $profile->import()->create(['source' => $source, 'filename' => mb_substr($filename, 0, 255), ...$attributes]);
    }

    private function upload(mixed $file): TemporaryUploadedFile
    {
        abort_unless($file instanceof TemporaryUploadedFile, 422);

        return $file;
    }

    /** @return Draft|null */
    private function draft(): ?array
    {
        $import = $this->profile()->import()->first();

        return $import?->status === ProfileImport::READY ? $import->data : null;
    }

    private function profile(): Profile
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->careerProfile();
    }
}
