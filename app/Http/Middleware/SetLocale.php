<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the visitor chose, or else the best match from their browser.
 */
final class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, string> $locales */
        $locales = config('app.locales');
        $chosen = $request->session()->get('locale');

        $locale = is_string($chosen) && array_key_exists($chosen, $locales)
            ? $chosen
            : $request->getPreferredLanguage(array_keys($locales));

        App::setLocale($locale ?? config()->string('app.locale'));

        return $next($request);
    }
}
