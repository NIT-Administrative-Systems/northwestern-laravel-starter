<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Domains\Auth\Mail\LoginCodeMail;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class MailFooterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'northwestern-filament-theme.unit.name' => 'Example Office',
            'northwestern-filament-theme.unit.address' => '633 Clark Street',
            'northwestern-filament-theme.unit.city' => 'Evanston, IL 60208',
            'northwestern-filament-theme.unit.phone' => '847-555-0100',
            'northwestern-filament-theme.unit.email' => 'office@northwestern.edu',
        ]);
    }

    public function test_html_mail_footer_has_the_unit_and_the_required_links(): void
    {
        $html = $this->loginCodeMail()->render();

        $this->assertStringContainsString('Example Office', $html);
        $this->assertStringContainsString('633 Clark Street, Evanston, IL 60208', $html);
        $this->assertStringContainsString('office@northwestern.edu', $html);
        $this->assertStringContainsString('href="https://www.northwestern.edu/accessibility/report/"', $html);
        $this->assertStringContainsString('href="https://www.northwestern.edu/privacy/"', $html);
        $this->assertStringContainsString('Northwestern University. All rights reserved.', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
    }

    public function test_text_mail_footer_spells_out_the_links(): void
    {
        $text = (string) resolve(Markdown::class)->renderText('mail.auth.login-code', ['code' => '123456', 'expiresIn' => '10 minutes', 'signInUrl' => 'https://example.test/app/login/email']);

        $this->assertStringContainsString('Example Office | 633 Clark Street, Evanston, IL 60208 | 847-555-0100 | office@northwestern.edu', $text);
        $this->assertStringContainsString('Accessibility: https://www.northwestern.edu/accessibility/report/', $text);
        $this->assertStringContainsString('Privacy Statement: https://www.northwestern.edu/privacy/', $text);
    }

    public function test_empty_unit_fields_are_left_out(): void
    {
        config(['northwestern-filament-theme.unit.phone' => '']);

        $text = (string) resolve(Markdown::class)->renderText('mail.auth.login-code', ['code' => '123456', 'expiresIn' => '10 minutes', 'signInUrl' => 'https://example.test/app/login/email']);

        $this->assertStringContainsString('Example Office | 633 Clark Street, Evanston, IL 60208 | office@northwestern.edu', $text);
    }

    public function test_mail_footer_includes_the_fax_when_set(): void
    {
        config(['northwestern-filament-theme.unit.fax' => '847-555-0199']);

        $text = (string) resolve(Markdown::class)->renderText('mail.auth.login-code', ['code' => '123456', 'expiresIn' => '10 minutes', 'signInUrl' => 'https://example.test/app/login/email']);

        $this->assertStringContainsString('847-555-0100 | Fax 847-555-0199 | office@northwestern.edu', $text);
    }

    public function test_mail_footer_matches_the_page_footer_when_unit_details_are_unset(): void
    {
        config(['northwestern-filament-theme.unit' => array_fill_keys(['name', 'address', 'city', 'phone', 'fax', 'email'], null)]);

        $text = (string) resolve(Markdown::class)->renderText('mail.auth.login-code', ['code' => '123456', 'expiresIn' => '10 minutes', 'signInUrl' => 'https://example.test/app/login/email']);

        $this->assertStringContainsString('Information Technology | 1800 Sherman Ave, Evanston, IL 60201', $text);
    }

    private function loginCodeMail(): LoginCodeMail
    {
        return new LoginCodeMail(Crypt::encryptString('123456'), CarbonImmutable::now()->addMinutes(10), 'https://example.test/app/login/email');
    }
}
