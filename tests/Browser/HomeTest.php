<?php

declare(strict_types=1);

use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPage;

it('shows guests the landing page with a way to sign in', function () {
    visit('/')
        ->assertPathIs('/')
        // A selector needs CSS punctuation, or the plugin looks for it as text.
        ->assertSeeIn('main h1:first-of-type', config('app.name'))
        ->click('@sign-in-link')
        ->assertPathIs('/app/login');
});

it('sends a signed-in person to the app panel', function () {
    $this->signInAsNorthwesternUser();

    visit('/')->assertPathIs('/app');
});

it('links administrators to the administration panel from the user menu', function () {
    $this->signInAsSuperAdministrator();

    $page = visit('/app');
    FilamentPage::openUserMenu($page);

    $page->click('@admin-panel-link')->assertPathIs('/administration');
});

it("doesn't show the administration link to people without access", function () {
    $this->signInAsNorthwesternUser();

    $page = visit('/app');
    FilamentPage::openUserMenu($page);

    $page->assertVisible('@account-menu-link')->assertMissing('@admin-panel-link');
});
