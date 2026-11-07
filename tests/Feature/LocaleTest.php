<?php

declare(strict_types=1);

it('shows the home page in English by default', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="en">', escape: false)
        ->assertSee('Your whole job search, from one career profile.');
});

it("follows the browser's language", function (): void {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
        ->get('/')
        ->assertSee('<html lang="de">', escape: false)
        ->assertSee('Ihre gesamte Jobsuche, aus einem einzigen Karriereprofil.');
});

it('remembers a chosen language over the browser', function (): void {
    $this->from('/')->post('/locale', ['locale' => 'de'])->assertRedirect('/');

    $this->withHeader('Accept-Language', 'en')
        ->get('/')
        ->assertSee('<html lang="de">', escape: false);
});

it('refuses a language it does not support', function (): void {
    $this->from('/')->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');

    $this->get('/')->assertSee('<html lang="en">', escape: false);
});
