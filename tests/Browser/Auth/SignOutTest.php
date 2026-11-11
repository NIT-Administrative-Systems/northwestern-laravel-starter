<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPage;

it('signs out from either panel and shuts the panels', function (string $panel) {
    $this->signInAsSuperAdministrator();

    $page = visit($panel);
    FilamentPage::openUserMenu($page);

    $page->click('@sign-out-menu-link')->assertPathIs('/app/login');

    $page->navigate('/administration')->assertPathIs('/app/login');
})->with(['the app panel' => '/app', 'the administration panel' => '/administration']);

// A page left open until the session expired still signs out, instead of showing Page Expired.
it('signs out from a page whose session expired', function (bool $sendsFetchMetadata) {
    // Laravel skips CSRF verification in tests: check requests as a deployed application does.
    // Laravel accepts a same-origin request by its Sec-Fetch-Site header alone; a browser that
    // doesn't send it, or doesn't over plain HTTP, has only the page's token to offer.
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter'], $sendsFetchMetadata) extends PreventRequestForgery
    {
        public function __construct($app, $encrypter, private readonly bool $sendsFetchMetadata)
        {
            parent::__construct($app, $encrypter);
        }

        public function handle($request, Closure $next)
        {
            if (! $this->sendsFetchMetadata) {
                $request->headers->remove('Sec-Fetch-Site');
            }

            return parent::handle($request, $next);
        }

        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    $this->signInAsNorthwesternUser();

    $page = visit('/app');
    FilamentPage::openUserMenu($page);

    // The session expires, and the page's token with it.
    $this->travel(config('session.lifetime') + 1)->minutes();
    session()->flush();

    $page->click('@sign-out-menu-link')
        ->assertPathIs('/app/login')
        ->assertDontSee('Page Expired');
})->with(['a browser that sends Fetch Metadata' => true, 'a browser that does not' => false]);
