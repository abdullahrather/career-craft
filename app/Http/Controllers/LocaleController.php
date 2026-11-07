<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController
{
    /** Remembers the language for the visit, and for a signed-in person's emails too. */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array<string, string> $locales */
        $locales = config('app.locales');
        $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys($locales))],
        ]);
        $locale = $request->string('locale')->toString();

        $request->session()->put('locale', $locale);
        $user = $request->user();
        if ($user instanceof User) {
            $user->update(['locale' => $locale]);
        }

        return back();
    }
}
