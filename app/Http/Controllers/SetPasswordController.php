<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * A first password for someone who signs in only with Google or GitHub, set in the settings
 * while signed in, as their sign-in already shows it's them. Changing a password that exists
 * needs the current one (Fortify's user-password.update).
 */
final class SetPasswordController
{
    use PasswordValidationRules;

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_if($user->password !== null, 403);

        $request->validateWithBag('updatePassword', ['password' => $this->passwordRules()]);
        $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();

        return back()->with('status', __('Password set. You can now also sign in with your email and password.'));
    }
}
