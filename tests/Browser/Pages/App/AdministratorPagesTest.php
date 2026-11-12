<?php

declare(strict_types=1);

use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * The app panel pages only administrators see work and pass axe.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

it('is healthy on the app panel pages only administrators see', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect(FilamentPages::in('app'))->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);
