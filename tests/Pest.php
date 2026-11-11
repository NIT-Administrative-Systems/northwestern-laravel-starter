<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests written as Pest functions run on
| the application's TestCase; unit tests run
| on PHPUnit's, without the application.
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
| They drive a real browser through Pest's browser plugin
| and Playwright, and aren't part of the default run:
| use `composer test:browser` after `pnpm build`.
|
| Chassis adds the health and accessibility expectations.
|
| These are the rules every page is checked against.
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
