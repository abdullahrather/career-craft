<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController
{
    /** Remembers the visitor's language for the rest of their visit. */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array<string, string> $locales */
        $locales = config('app.locales');
        $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys($locales))],
        ]);

        $request->session()->put('locale', $request->string('locale')->toString());

        return back();
    }
}
