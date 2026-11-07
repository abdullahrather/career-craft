<?php

declare(strict_types=1);

use App\Livewire\Settings\Devices;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function session_row(User $user, string $id, string $agent): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

it('lists the devices someone is signed in on', function (): void {
    $user = User::factory()->create();
    session_row($user, 'phone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/605.1');

    Livewire::actingAs($user)->test(Devices::class)->assertSee('Safari on iOS')->assertSee('203.0.113.7');
});

it('signs out every other device after checking the password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('the-password')]);
    session_row($user, 'phone', 'Firefox/130 Android');
    session_row($user, 'laptop', 'Edg/130 Windows');

    Livewire::actingAs($user)->test(Devices::class)
        ->set('password', 'wrong')->call('signOutOthers')->assertHasErrors('password');
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

    Livewire::actingAs($user)->test(Devices::class)
        ->set('password', 'the-password')->call('signOutOthers')->assertHasNoErrors()->assertSet('signedOut', true);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('describes devices in German too', function (): void {
    app()->setLocale('de');

    expect(Devices::describe('Firefox/130 Windows'))->toBe('Firefox auf Windows')
        ->and(Devices::describe(''))->toBe('Unbekannter Browser auf einem unbekannten System');
});
