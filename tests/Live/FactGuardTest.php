<?php

declare(strict_types=1);

use App\Ai\FactGuard;
use Illuminate\Support\Facades\Http;

/*
 * The fact guard against the real local model (Ollama), on tricky suggestions: invented
 * employers, schools, titles, technologies, numbers and results, and honest rewordings
 * that must pass. Not part of the normal run or CI: `vendor/bin/pest tests/Live`, with
 * Ollama running and the model downloaded. Skipped when Ollama can't be reached.
 */

const LIVE_FACTS = <<<'TXT'
Headline: Backend developer, PHP and Laravel
Summary: I build backends and APIs for logistics companies and want to lead a team next.
Job: Backend developer at Nordlicht Logistik, Hamburg DE, 2021-03 to now
  - worked on the order api
  - made the checkout faster by 40%
  - helped new colleagues
Job: Working student at Hafenblick Software, Hamburg DE, 2018-10 to 2021-02
  - built internal tools in PHP
Education: B.Sc. Computer science at Universität Hamburg, 2016-10 to 2020-09
Skills: PHP, Laravel, PostgreSQL, Docker
Languages: German native, English C1
TXT;

const LIVE_JOBS = [['title' => 'Backend developer', 'employer' => 'Nordlicht Logistik'], ['title' => 'Working student', 'employer' => 'Hafenblick Software']];

function liveCheck(string $original, string $suggestion): array
{
    return app(FactGuard::class)->check($suggestion, LIVE_FACTS."\nText being edited: {$original}", null, LIVE_JOBS);
}

beforeEach(function (): void {
    try {
        $up = Http::timeout(3)->get(rtrim((string) config('ai.providers.ollama.url'), '/').'/api/tags')->successful();
    } catch (Throwable) {
        $up = false;
    }
    if (! $up) {
        $this->markTestSkipped('Ollama is not running.');
    }
});

it('flags every invented fact', function (string $original, string $suggestion, array $mustFlag): void {
    $flagged = mb_strtolower(implode(' | ', array_column(liveCheck($original, $suggestion), 'quote')));

    foreach ($mustFlag as $words) {
        expect(array_filter($words, fn (string $word): bool => str_contains($flagged, mb_strtolower($word))))
            ->not->toBeEmpty('Nothing of ['.implode(', ', $words)."] was flagged in: {$suggestion}");
    }
})->with([
    'a new technology' => ['worked on the order api', 'Developed the order API with Kubernetes', [['Kubernetes']]],
    'an invented result and number' => ['worked on the order api', 'Developed the order API, cutting response times by 30%', [['30', 'response times']]],
    'an invented team and title' => ['helped new colleagues', 'Led a team of 8 engineers', [['8'], ['Led']]],
    'a famous employer and title' => ['worked on the order api', 'Served as CEO of Google, developing the order API', [['CEO'], ['Google']]],
    'a degree and school' => ['built internal tools in PHP', 'Built internal tools in PHP while completing a PhD at MIT', [['PhD'], ['MIT']]],
    'a senior title' => ['worked on the order api', 'Senior backend developer who built the order API', [['Senior']]],
    'a title moved to another employer' => ['built internal tools in PHP', 'Backend developer at Hafenblick Software, building internal tools in PHP', [['Backend developer']]],
    'a certificate' => ['helped new colleagues', 'Mentored new colleagues and earned an AWS certification', [['AWS']]],
    'invented revenue' => ['made the checkout faster by 40%', 'Sped up checkout by 40%, increasing revenue by 2 million euros', [['2', 'revenue', 'million']]],
    'invented customers' => ['worked on the order api', 'Developed the order API used by Amazon and Zalando', [['Amazon', 'Zalando']]],
    'a new customer' => ['built internal tools in PHP', 'Built internal tools in PHP for Siemens', [['Siemens']]],
    'a master\'s degree' => ['I build backends and APIs for logistics companies and want to lead a team next.', 'Backend developer with a Master\'s degree, building APIs for logistics companies.', [['Master']]],
    'invented clients, in German' => ['Betreuung von Kunden im Support', 'Betreute Kunden im Support und gewann 3 Großkunden', [['3', 'Großkunden']]],
]);

it('lets honest rewordings through, with few false flags', function (): void {
    $honest = [
        ['worked on the order api', 'Developed the order API'],
        ['made the checkout faster by 40%', 'Sped up checkout by 40%'],
        ['helped new colleagues', 'Coached new colleagues during their onboarding'],
        ['made the checkout faster by 40%', 'Bearbeitungszeit des Checkouts um 40 % verkürzt'],
        ['helped new colleagues', 'Unterstützte neue Kolleginnen und Kollegen bei der Einarbeitung'],
        ['worked on the order api', 'Built and maintained the order API'],
        ['built internal tools in PHP', 'Developed internal PHP tools for the team'],
        ['made the checkout faster by 40%', 'Improved checkout speed by 40% using PostgreSQL'],
        ['helped new colleagues', 'Helped new colleagues settle in'],
        ['built internal tools in PHP', 'Built internal tools in PHP and Laravel'],
        ['Reduced support tickets after redesigning onboarding', 'Reduced support tickets by [X%] by redesigning onboarding'],
        ['did some testing', 'Conducted testing'],
        ['basically i was the guy who fixed the stuff nobody else wanted to touch, lol', 'Handled tasks that others were reluctant to take on.'],
        ['I build backends and APIs for logistics companies and want to lead a team next.', 'Backend developer building APIs for logistics companies, aiming to lead a team.'],
    ];

    $flagged = array_filter($honest, fn (array $case): bool => liveCheck(...$case) !== []);

    // A false flag costs the candidate one click to confirm; more than a few would make the flags noise.
    expect(count($flagged))->toBeLessThanOrEqual(2, 'Flagged honest rewordings: '.implode(' | ', array_column($flagged, 1)));
});
