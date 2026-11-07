<?php

declare(strict_types=1);

use App\Livewire\Profile\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

it('saves the photo as a 600 × 800 portrait, whatever shape it arrives in', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Photo::class)
        ->set('photo', UploadedFile::fake()->image('me.png', 1600, 900))
        ->call('save')
        ->assertHasNoErrors();

    $path = $user->careerProfile()->photo_path;
    [$width, $height, $type] = getimagesizefromstring((string) Storage::disk('local')->get($path));
    expect([$width, $height, $type])->toBe([600, 800, IMAGETYPE_JPEG]);
});

it('refuses files that are not pictures, and tiny ones', function (): void {
    Livewire::actingAs(User::factory()->create())->test(Photo::class)
        ->set('photo', UploadedFile::fake()->create('me.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photo')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 60, 80))
        ->call('save')
        ->assertHasErrors('photo')
        // A small file can still have more pixels than the server can decode.
        ->set('photo', UploadedFile::fake()->image('huge.png', 8001, 200))
        ->call('save')
        ->assertHasErrors('photo');
});

it('replaces and removes the photo, and deletes the old file', function (): void {
    $user = User::factory()->create();
    $photo = Livewire::actingAs($user)->test(Photo::class)
        ->set('photo', UploadedFile::fake()->image('first.jpg', 600, 800))->call('save');
    $first = $user->careerProfile()->photo_path;

    $photo->set('photo', UploadedFile::fake()->image('second.jpg', 600, 800))->call('save');
    $second = $user->careerProfile()->photo_path;
    Storage::disk('local')->assertMissing($first)->assertExists($second);

    $photo->call('remove');
    Storage::disk('local')->assertMissing($second);
    expect($user->careerProfile()->photo_path)->toBeNull();
});

it('shows the photo to its owner only', function (): void {
    $owner = User::factory()->create();
    Livewire::actingAs($owner)->test(Photo::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 600, 800))->call('save');

    $this->actingAs($owner)->get('/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs(User::factory()->create())->get('/profile/photo')->assertNotFound();
    auth()->logout();
    $this->get('/profile/photo')->assertRedirect('/login');
});
