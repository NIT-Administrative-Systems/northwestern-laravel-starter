<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\StarterCheckCommand;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[CoversClass(StarterCheckCommand::class)]
final class StarterCheckCommandTest extends TestCase
{
    private string $tempVersionFile;

    protected function setUp(): void
    {
        // Unset CI env vars so shouldSkip() doesn't bail in GitHub Actions.
        unset($_ENV['CI'], $_SERVER['CI']);

        parent::setUp();

        $this->tempVersionFile = tempnam(sys_get_temp_dir(), 'starter-version-') ?: '';

        Cache::forget('starter-check-releases');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempVersionFile)) {
            @unlink($this->tempVersionFile);
        }

        unset($_ENV['CI'], $_SERVER['CI']);

        parent::tearDown();
    }

    public function test_displays_notice_when_behind(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.1', '2026-02-20'),
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('2 releases behind', $output);
        $this->assertStringContainsString('v1.4.0', $output);
        $this->assertStringContainsString('v1.6.1', $output);
        $this->assertStringContainsString('v1.6.0', $output);
        $this->assertStringContainsString('2026-02-20', $output);
        $this->assertStringContainsString('compare/v1.4.0...v1.6.1', $output);
        $this->assertStringContainsString('CHANGELOG.md', $output);
    }

    public function test_shows_release_links(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.5.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('releases/tag/v1.5.0', $output);
    }

    public function test_shows_suppress_message_with_latest_version(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('suppress these notices', $output);
        $this->assertStringContainsString('version: v1.6.0', $output);
        $this->assertStringContainsString('.starter-version.yaml', $output);
    }

    public function test_pluralizes_single_release(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.5.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('1 release behind', $output);
        $this->assertStringNotContainsString('1 releases', $output);
    }

    public function test_pluralizes_multiple_releases(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-02-20'),
                $this->makeRelease('v1.5.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('2 releases behind', $output);
    }

    public function test_filters_out_draft_releases(): void
    {
        $this->writeVersionFile('v1.4.0');

        $draft = $this->makeRelease('v1.7.0', '2026-03-01');
        $draft['draft'] = true;

        Http::fake([
            'api.github.com/*' => Http::response([
                $draft,
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringNotContainsString('v1.7.0', $output);
        $this->assertStringContainsString('v1.6.0', $output);
    }

    public function test_filters_out_prerelease_releases(): void
    {
        $this->writeVersionFile('v1.4.0');

        $prerelease = $this->makeRelease('v1.7.0-rc1', '2026-03-01');
        $prerelease['prerelease'] = true;

        Http::fake([
            'api.github.com/*' => Http::response([
                $prerelease,
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringNotContainsString('v1.7.0-rc1', $output);
        $this->assertStringContainsString('v1.6.0', $output);
    }

    public function test_sorts_releases_newest_first(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.5.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
                $this->makeRelease('v1.6.0', '2026-02-20'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $v160Pos = strpos($output, 'v1.6.0');
        $v150Pos = strpos($output, 'v1.5.0');

        $this->assertNotFalse($v160Pos);
        $this->assertNotFalse($v150Pos);
        $this->assertLessThan($v150Pos, $v160Pos);
    }

    public function test_version_without_v_prefix_works(): void
    {
        $this->writeVersionFile('1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertStringContainsString('1.4.0', $output);
        $this->assertStringContainsString('v1.6.0', $output);
    }

    public function test_silently_exits_when_up_to_date(): void
    {
        $this->writeVersionFile('v1.6.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertEmpty(trim($output));
    }

    public function test_silently_exits_when_version_file_missing(): void
    {
        @unlink($this->tempVersionFile);

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_silently_exits_when_version_format_invalid(): void
    {
        $this->writeVersionFile('not-a-version');

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_silently_exits_when_version_file_has_no_version_key(): void
    {
        file_put_contents($this->tempVersionFile, "some_other_key: v1.0.0\n");

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_silently_exits_when_yaml_is_malformed(): void
    {
        file_put_contents($this->tempVersionFile, ":\n  invalid: [yaml\n");

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_silently_exits_when_api_fails(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response(null, 500),
        ]);

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_silently_exits_when_connection_fails(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_caches_api_response(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->artisan('starter:check', ['--version-file' => $this->tempVersionFile])->assertExitCode(0);
        $this->artisan('starter:check', ['--version-file' => $this->tempVersionFile])->assertExitCode(0);

        Http::assertSentCount(1);
    }

    public function test_does_not_cache_failed_requests(): void
    {
        $this->writeVersionFile('v1.4.0');

        Http::fake([
            'api.github.com/*' => Http::response(null, 500),
        ]);

        $this->artisan('starter:check', ['--version-file' => $this->tempVersionFile])->assertExitCode(0);

        $this->assertNull(Cache::get('starter-check-releases'));
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    #[TestWith(['qa'])]
    #[TestWith(['develop'])]
    public function test_skips_in_non_local_environments(string $environment): void
    {
        $this->writeVersionFile('v1.4.0');

        App::shouldReceive('environment')
            ->with('production', 'staging', 'qa', 'develop')
            ->andReturn(true);

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertEmpty(trim($output));
        Http::assertNothingSent();
    }

    public function test_skips_when_ci_env_variable_is_set(): void
    {
        $this->writeVersionFile('v1.4.0');

        $_ENV['CI'] = 'true';

        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $output = Artisan::output();

        $this->assertEmpty(trim($output));
        Http::assertNothingSent();
    }

    public function test_silently_exits_when_database_is_unavailable(): void
    {
        $this->writeVersionFile('v1.4.0');

        Cache::shouldReceive('get')
            ->with('starter-check-releases')
            ->andThrow(new \PDOException('SQLSTATE[08006] connection to server failed: database does not exist'));

        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('starter:check', ['--version-file' => $this->tempVersionFile]);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(trim(Artisan::output()));
    }

    public function test_defaults_to_project_root_version_file(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                $this->makeRelease('v1.6.0', '2026-01-10'),
                $this->makeRelease('v1.4.0', '2025-11-15'),
            ]),
        ]);

        $this->artisan('starter:check')
            ->assertExitCode(0);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRelease(string $tag, string $date, string $body = ''): array
    {
        return [
            'tag_name' => $tag,
            'name' => $tag,
            'body' => $body,
            'published_at' => $date . 'T00:00:00Z',
            'html_url' => "https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/releases/tag/{$tag}",
            'draft' => false,
            'prerelease' => false,
        ];
    }

    private function writeVersionFile(string $version): void
    {
        file_put_contents($this->tempVersionFile, "version: {$version}\n");
    }
}
