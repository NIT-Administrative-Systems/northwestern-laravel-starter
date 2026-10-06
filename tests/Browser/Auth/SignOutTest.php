<?php

declare(strict_types=1);

use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPage;

it('signs out from either panel and shuts the panels', function (string $panel) {
    $this->signInAsSuperAdministrator();

    $page = visit($panel);
    FilamentPage::openUserMenu($page);

    $page->click('@sign-out-menu-link')->assertPathIs('/app/login');

    $page->navigate('/administration')->assertPathIs('/app/login');
})->with(['the app panel' => '/app', 'the administration panel' => '/administration']);
