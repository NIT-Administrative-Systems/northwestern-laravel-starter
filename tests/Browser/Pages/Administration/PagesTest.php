<?php

declare(strict_types=1);

use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every administration page works and passes axe for a super administrator.
 *
 * Pages are found from the panel. Pages that need a record are in
 * RecordPagesTest, and the layout checks are in LayoutTest.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

it('is healthy on every administration page', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect(FilamentPages::in('administration'))->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);

it("is healthy on the administration panel's dashboard on a phone", function () {
    expect(visit('/administration'))->toBeHealthyOnMobile();
});
