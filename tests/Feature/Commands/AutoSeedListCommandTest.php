<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\AutoSeedListCommand;
use App\Domains\Core\Database\ValueObjects\SeederInfo;
use App\Domains\Core\Services\IdempotentSeederResolver;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AutoSeedListCommand::class)]
class AutoSeedListCommandTest extends TestCase
{
    public function test_outputs_warning_if_no_seeders_found(): void
    {
        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) {
            $mock->expects('discover')->andReturn();
        });

        $this->artisan('db:seed:list')
            ->expectsOutputToContain('No seeders found with the #[AutoSeed] attribute.')
            ->assertExitCode(0);
    }

    public function test_format_dependencies_with_zero_dependencies(): void
    {
        $seederInfo = new SeederInfo(
            className: 'App\\Seeders\\FooSeeder',
            dependsOn: []
        );

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list');

        $output = Artisan::output();
        $plain = $this->stripAnsi($output);

        $this->assertStringContainsString('none', $plain);
    }

    public function test_format_dependencies_with_one_dependency(): void
    {
        $seederInfo = new SeederInfo(
            className: 'App\\Seeders\\FooSeeder',
            /** @phpstan-ignore-next-line  */
            dependsOn: ['App\\Seeders\\DepASeeder']
        );

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list');

        $output = Artisan::output();
        $plain = $this->stripAnsi($output);

        $this->assertStringContainsString('DepASeeder', $plain);
    }

    public function test_format_dependencies_with_two_dependencies(): void
    {
        $seederInfo = new SeederInfo(
            className: 'App\\Seeders\\FooSeeder',
            /** @phpstan-ignore-next-line  */
            dependsOn: [
                'App\\Seeders\\DepASeeder',
                'App\\Seeders\\DepBSeeder',
            ]
        );

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list');

        $output = Artisan::output();
        $plain = $this->stripAnsi($output);

        $this->assertStringContainsString('DepASeeder', $plain);
        $this->assertStringContainsString('DepBSeeder', $plain);
        $this->assertStringNotContainsString('+', $output, 'Should NOT include "+ more" for exactly 2 dependencies');
    }

    public function test_format_dependencies_with_more_than_two_dependencies(): void
    {
        $seederInfo = new SeederInfo(
            className: 'App\\Seeders\\FooSeeder',
            /** @phpstan-ignore-next-line  */
            dependsOn: [
                'App\\Seeders\\DepASeeder',
                'App\\Seeders\\DepBSeeder',
                'App\\Seeders\\DepCSeeder',
            ]
        );

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list');

        $output = Artisan::output();
        $plain = $this->stripAnsi($output);

        $this->assertStringContainsString('DepASeeder', $plain);
        $this->assertStringContainsString('DepBSeeder', $plain);

        $this->assertStringContainsString('+1 more', $plain);

        $this->assertStringNotContainsString('DepCSeeder', $plain);
    }

    public function test_outputs_seeders_as_json(): void
    {
        $seederInfo = new SeederInfo(
            className: 'App\\Seeders\\FooSeeder',
            /** @phpstan-ignore-next-line  */
            dependsOn: ['App\\Seeders\\BarSeeder']
        );

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list', ['--json' => true]);

        $outputText = Artisan::output();
        $json = json_decode($outputText, true);

        $this->assertIsArray($json);
        $this->assertSame(1, $json['total']);
        $this->assertSame('App\\Seeders\\FooSeeder', $json['seeders'][0]['class']);
        $this->assertSame('FooSeeder', $json['seeders'][0]['short_name']);
        $this->assertSame(['App\\Seeders\\BarSeeder'], $json['seeders'][0]['depends_on']);
    }

    public function test_outputs_mermaid_diagram(): void
    {
        /** @phpstan-ignore-next-line  */
        $seederInfo = new SeederInfo(className: 'App\\Seeders\\FooSeeder', dependsOn: ['App\\Seeders\\BarSeeder']);

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->artisan('db:seed:list', ['--mermaid' => true])
            ->expectsOutputToContain('```mermaid')
            ->expectsOutputToContain('graph TD')
            ->expectsOutputToContain('["FooSeeder"]')
            ->expectsOutputToContain('-->')
            ->expectsOutputToContain('📋 Next Steps:')
            ->assertExitCode(0);
    }

    public function test_outputs_dependency_tree_when_requested(): void
    {
        /** @phpstan-ignore-next-line  */
        $seederInfo = new SeederInfo(className: 'App\\Seeders\\FooSeeder', dependsOn: ['App\\Seeders\\BarSeeder']);

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->artisan('db:seed:list', ['--show-dependencies' => true])
            ->expectsOutputToContain('Dependency Tree:')
            ->expectsOutputToContain('📦')
            ->expectsOutputToContain('├──')
            ->assertExitCode(0);
    }

    public function test_outputs_table_and_hint_by_default(): void
    {
        $seederInfo = new SeederInfo(className: 'App\\Seeders\\FooSeeder', dependsOn: []);

        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) use ($seederInfo) {
            $mock->expects('discover')->andReturn([$seederInfo]);
        });

        $this->withoutMockingConsoleOutput()
            ->artisan('db:seed:list');

        $output = Artisan::output();

        $this->assertStringContainsString('Seeder', $output);
        $this->assertStringContainsString('FooSeeder', $output);
        $this->assertStringContainsString('none', $output);
        $this->assertStringContainsString('Use the --show-dependencies option', $output);
    }

    public function test_outputs_error_on_exception(): void
    {
        $this->mock(IdempotentSeederResolver::class, function (MockInterface $mock) {
            /** @phpstan-ignore-next-line  */
            $mock->expects('discover')->andReturnUsing(function () {
                throw new \RuntimeException('Foo');
            });
        });

        $this->artisan('db:seed:list')
            ->expectsOutputToContain('Failed to discover seeders')
            ->expectsOutputToContain('Foo')
            ->assertExitCode(1);
    }

    private function stripAnsi(string $text): string
    {
        return preg_replace('/\e\[([;\d]+)?m/', '', $text) ?? $text;
    }
}
