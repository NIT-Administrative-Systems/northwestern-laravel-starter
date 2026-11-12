<?php

declare(strict_types=1);

use App\Domains\Support\Models\Changelog;
use App\Domains\User\Models\User;
use Northwestern\SysDev\Chassis\Testing\Browser\ErrorPages;
use Northwestern\SysDev\Chassis\Testing\Browser\Layout;

/*
 * Every page a guest can reach fits a 320 pixel phone.
 *
 * Add your application's public pages to the list of paths.
 */

it('fits a 320 pixel phone, with controls a finger can tap', function () {
    User::factory()->affiliate()->create(['email' => 'partner@example.com']);
    Changelog::factory()->create();
    $paths = [
        '/',
        '/app/login',
        '/app/login/email',
        '/support/changelog',
        ...array_map(ErrorPages::path(...), [401, 402, 403, 404, 419, 429, 500, 503]),
    ];

    expect($paths)->toAllFitEachWidth(widths: [320], tapTargets: true);

    $page = Layout::visit('/app/login/email', width: 320)
        ->type('@email-input', 'partner@example.com')
        ->click('@continue-button')
        ->assertVisible('@code-input');

    expect($page)->toFitTheScreen(tapTargets: true);
});
