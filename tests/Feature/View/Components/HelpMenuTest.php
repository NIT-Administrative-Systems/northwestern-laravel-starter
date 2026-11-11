<?php

declare(strict_types=1);

namespace Tests\Feature\View\Components;

use App\Domains\User\Models\User;
use App\View\Components\HelpMenu;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HelpMenu::class)]
final class HelpMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['support.enabled' => true, 'support.documentation_url' => 'https://docs.example.edu/']);
    }

    public function test_visitors_see_the_changelog_and_documentation(): void
    {
        $menu = Blade::render('<x-help-menu />');

        $this->assertStringContainsString('Changelog', $menu);
        $this->assertStringContainsString('href="https://docs.example.edu/"', $menu);
        $this->assertStringNotContainsString('Announcements', $menu);
        $this->assertStringNotContainsString('Contact Support', $menu);
    }

    public function test_people_signed_in_also_see_announcements_and_contact_support(): void
    {
        $this->actingAs(User::factory()->create());

        $menu = Blade::render('<x-help-menu />');

        $this->assertStringContainsString(url('/app/announcements'), $menu);
        $this->assertStringContainsString(url('/app/support/contact'), $menu);
    }

    public function test_contact_support_is_left_out_while_support_is_off(): void
    {
        config(['support.enabled' => false, 'support.documentation_url' => '']);
        $this->actingAs(User::factory()->create());

        $menu = Blade::render('<x-help-menu />');

        $this->assertStringNotContainsString('Contact Support', $menu);
        $this->assertStringNotContainsString('Documentation', $menu);
    }
}
