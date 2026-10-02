<?php

declare(strict_types=1);

namespace Tests\Feature\View\Components;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\View\Components\SentryBrowser;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SentryBrowser::class)]
final class SentryBrowserTest extends TestCase
{
    private const string DSN = 'https://public-key@o123.ingest.us.sentry.io/4567';

    public function test_renders_nothing_without_a_dsn(): void
    {
        config(['sentry.dsn' => null]);

        $this->assertSame('', trim(Blade::render('<x-sentry-browser />')));
    }

    public function test_renders_the_sdk_config_routed_through_the_tunnel(): void
    {
        config([
            'sentry.dsn' => self::DSN,
            'northwestern-theme.sentry-enable-apm-js' => true,
            'northwestern-theme.sentry-traces-sample-rate' => 0.25,
        ]);

        $config = $this->renderedConfig();

        $this->assertSame(self::DSN, $config['dsn']);
        $this->assertSame('testing', $config['environment']);
        $this->assertSame('/sentry/tunnel', $config['tunnel']);
        $this->assertTrue($config['tracing']);
        $this->assertEqualsWithDelta(0.25, $config['tracesSampleRate'], PHP_FLOAT_EPSILON);
        $this->assertNull($config['user']);
    }

    public function test_includes_the_same_user_context_as_php_reports(): void
    {
        config(['sentry.dsn' => self::DSN]);
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertSame($user->id, $this->renderedConfig()['user']['id']);
        $this->assertSame($user->username, $this->renderedConfig()['user']['username']);
    }

    public function test_both_panels_load_the_sdk(): void
    {
        config(['sentry.dsn' => self::DSN]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->actingAs($admin)->get('/app')->assertOk()->assertSee('sentry-browser-config', escape: false);
        $this->actingAs($admin)->get('/administration')->assertOk()->assertSee('sentry-browser-config', escape: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function renderedConfig(): array
    {
        $html = Blade::render('<x-sentry-browser />');

        $this->assertMatchesRegularExpression('/<script id="sentry-browser-config" type="application\/json">(.+)<\/script>/', $html);
        preg_match('/<script id="sentry-browser-config" type="application\/json">(.+)<\/script>/', $html, $matches);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
