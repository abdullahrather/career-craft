<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\User;
use GdImage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The photo for a German CV. The browser frames it at 3:4; the server crops to 3:4 again
 * in case it wasn't, and saves a fresh 600 × 800 JPEG.
 */
final class Photo extends Component
{
    use WithFileUploads;

    public const int WIDTH = 600;

    public const int HEIGHT = 800;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function save(): void
    {
        $this->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=150,min_height=200,max_width=8000,max_height=8000']],
            attributes: ['photo' => __('Photo')],
        );
        $file = $this->photo;
        abort_unless($file instanceof TemporaryUploadedFile, 422);

        $content = file_get_contents($file->getRealPath());
        $image = is_string($content) ? @imagecreatefromstring($content) : false;
        $file->delete();
        $this->photo = null;
        if ($image === false) {
            $this->addError('photo', __('This picture could not be opened. Use a JPEG or PNG file.'));

            return;
        }

        $profile = $this->profile();
        $old = $profile->photo_path;
        $path = 'photos/'.$profile->id.'-'.Str::random(24).'.jpg';
        Storage::disk('local')->put($path, self::portrait($image));
        $profile->forceFill(['photo_path' => $path])->save();
        if ($old !== null) {
            Storage::disk('local')->delete($old);
        }
    }

    public function remove(): void
    {
        $profile = $this->profile();
        if ($profile->photo_path !== null) {
            Storage::disk('local')->delete($profile->photo_path);
            $profile->forceFill(['photo_path' => null])->save();
        }
    }

    public function render(): View
    {
        $profile = $this->profile();

        return view('livewire.profile.photo', [
            // The file name changes with every photo, so the browser never shows an old one.
            'url' => $profile->photo_path !== null ? route('profile.photo', ['v' => basename($profile->photo_path, '.jpg')]) : null,
        ]);
    }

    /**
     * The middle 3:4 of the picture at 600 × 800, as a JPEG. Drawing it afresh drops
     * everything else in the file, such as where the photo was taken.
     */
    public static function portrait(GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $cropWidth = min($width, (int) round($height * 3 / 4));
        $cropHeight = min($height, (int) round($cropWidth * 4 / 3));

        $portrait = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        // A transparent PNG gets a white background rather than black.
        imagefill($portrait, 0, 0, (int) imagecolorallocate($portrait, 255, 255, 255));
        imagecopyresampled($portrait, $image, 0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2), self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        ob_start();
        imagejpeg($portrait, null, 85);

        return (string) ob_get_clean();
    }

    private function profile(): Profile
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->careerProfile();
    }
}
