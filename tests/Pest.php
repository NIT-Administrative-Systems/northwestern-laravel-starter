<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
)->in('Feature');

uses(
    PHPUnit\Framework\TestCase::class,
)->in('Unit');

/*
|--------------------------------------------------------------------------
| Browser Tests
|--------------------------------------------------------------------------
|
| tests/Browser runs in a real browser through Pest's browser plugin and Playwright. It isn't
| part of the default run: use `composer test:browser`, after `pnpm build`. Chassis adds the
| health and accessibility expectations; these are the rules every page is checked against.
|
*/

uses(
    Tests\BrowserTestCase::class,
)->in('Browser');

Northwestern\SysDev\Chassis\Testing\Browser\Expectations::register();

Northwestern\SysDev\Chassis\Testing\Browser\Accessibility::configure(disableRules: [
    'duplicate-id',
    // Filament leaves the actions column's header cell empty in every table.
    'empty-table-header',
]);
