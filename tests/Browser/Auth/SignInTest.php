<?php

declare(strict_types=1);

use App\Domains\Auth\Mail\LoginCodeMail;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

it('offers email sign-in only while it is on', function (bool $localAuth) {
    config(['local-auth.enabled' => $localAuth]);

    $page = visit('/app/login')->assertSee('Sign In');

    $localAuth ? $page->assertVisible('@email-login') : $page->assertMissing('@email-login');
})->with(['on' => true, 'off' => false]);

it('signs a partner in with the code it emails them', function () {
    Mail::fake();
    $partner = User::factory()->affiliate()->create(['email' => 'partner@example.com']);

    $page = visit('/app/login/email')
        ->type('@email-input', 'partner@example.com')
        ->click('@continue-button')
        ->assertSee('Check Your Email');

    // The Continue button that had focus is gone; focus moves to the code.
    $page->assertScript("document.activeElement?.dataset.testid === 'code-input'");

    $mail = Mail::sent(LoginCodeMail::class)->sole();

    $page->type('@code-input', Crypt::decryptString($mail->encryptedCode))
        ->click('@verify-button')
        ->assertPathBeginsWith('/app')
        ->assertPathIsNot('/app/login/email')
        ->assertVisible('.fi-user-menu');

    expect(auth()->id())->toBe($partner->getKey());
});

it('refuses a wrong code', function () {
    Mail::fake();
    User::factory()->affiliate()->create(['email' => 'partner@example.com']);

    visit('/app/login/email')
        ->type('@email-input', 'partner@example.com')
        ->click('@continue-button')
        ->type('@code-input', '000000')
        ->click('@verify-button')
        ->assertSee("That code didn't work")
        ->assertPathIs('/app/login/email');

    expect(auth()->check())->toBeFalse();
});
