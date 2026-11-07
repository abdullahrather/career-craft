<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the visitor chose, then a signed-in person's saved language,
 * then the best match from their browser.
 */
final class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, string> $locales */
        $locales = config('app.locales');
        $user = $request->user();

        $candidates = [
            $request->session()->get('locale'),
            $user instanceof User ? $user->locale : null,
        ];
        $locale = collect($candidates)->first(
            fn (mixed $candidate): bool => is_string($candidate) && array_key_exists($candidate, $locales),
        );

        App::setLocale(is_string($locale)
            ? $locale
            : ($request->getPreferredLanguage(array_keys($locales)) ?? config()->string('app.locale')));

        return $next($request);
    }
}
