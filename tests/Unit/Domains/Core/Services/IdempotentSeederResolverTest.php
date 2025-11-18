<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services;

use App\Domains\Core\Services\IdempotentSeederResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(IdempotentSeederResolver::class)]
class IdempotentSeederResolverTest extends TestCase
{
    private string $testSeedersPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testSeedersPath = storage_path('framework/testing/seeders');

        if (! is_dir($this->testSeedersPath)) {
            mkdir($this->testSeedersPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testSeedersPath)) {
            $files = glob($this->testSeedersPath . '/*.php') ?: [];
            array_map(unlink(...), $files);
            rmdir($this->testSeedersPath);
        }

        parent::tearDown();
    }

    public function test_discovers_seeders_with_autoseed_attribute(): void
    {
        $this->createTestSeeder('SimpleSeeder', []);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(1, $seeders);
        $this->assertSame('SimpleSeeder', $seeders[0]->getShortName());
    }

    public function test_ignores_seeders_without_attribute(): void
    {
        $this->createTestSeeder('WithAttribute', []);
        $this->createSeederWithoutAttribute('WithoutAttribute');

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(1, $seeders);
        $this->assertSame('WithAttribute', $seeders[0]->getShortName());
    }

    public function test_ignores_abstract_seeders(): void
    {
        $this->createTestSeeder('ConcreteSeeder', []);
        $this->createAbstractSeeder('AbstractBaseSeeder');

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(1, $seeders);
        $this->assertSame('ConcreteSeeder', $seeders[0]->getShortName());
    }

    public function test_resolves_simple_dependencies(): void
    {
        $this->createTestSeeder('BaseSeeder', []);
        $this->createTestSeeder('DependentSeeder', ['BaseSeeder']);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(2, $seeders);
        $this->assertSame('BaseSeeder', $seeders[0]->getShortName());
        $this->assertSame('DependentSeeder', $seeders[1]->getShortName());
    }

    public function test_resolves_complex_dependency_chain(): void
    {
        $this->createTestSeeder('A', []);
        $this->createTestSeeder('B', ['A']);
        $this->createTestSeeder('C', ['B']);
        $this->createTestSeeder('D', ['A', 'B']);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $order = array_map(fn ($s) => $s->getShortName(), $seeders);

        $aIndex = array_search('A', $order, true);
        $bIndex = array_search('B', $order, true);
        $cIndex = array_search('C', $order, true);
        $dIndex = array_search('D', $order, true);

        // A must come before B, C, and D
        $this->assertLessThan($bIndex, $aIndex);
        $this->assertLessThan($cIndex, $aIndex);
        $this->assertLessThan($dIndex, $aIndex);

        // B must come before C and D
        $this->assertLessThan($cIndex, $bIndex);
        $this->assertLessThan($dIndex, $bIndex);
    }

    public function test_resolves_diamond_dependency_pattern(): void
    {
        // Diamond: A depends on nothing, B and C depend on A, D depends on both B and C
        $this->createTestSeeder('DiamondA', []);
        $this->createTestSeeder('DiamondB', ['DiamondA']);
        $this->createTestSeeder('DiamondC', ['DiamondA']);
        $this->createTestSeeder('DiamondD', ['DiamondB', 'DiamondC']);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $order = array_map(fn ($s) => $s->getShortName(), $seeders);

        $aIndex = array_search('DiamondA', $order, true);
        $bIndex = array_search('DiamondB', $order, true);
        $cIndex = array_search('DiamondC', $order, true);
        $dIndex = array_search('DiamondD', $order, true);

        // A must be first
        $this->assertLessThan($bIndex, $aIndex);
        $this->assertLessThan($cIndex, $aIndex);

        // D must be last
        $this->assertLessThan($dIndex, $bIndex);
        $this->assertLessThan($dIndex, $cIndex);
    }

    public function test_handles_multiple_independent_chains(): void
    {
        // Chain 1: A -> B
        $this->createTestSeeder('ChainA', []);
        $this->createTestSeeder('ChainB', ['ChainA']);

        // Chain 2: X -> Y (completely independent)
        $this->createTestSeeder('ChainX', []);
        $this->createTestSeeder('ChainY', ['ChainX']);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(4, $seeders);

        $order = array_map(fn ($s) => $s->getShortName(), $seeders);

        // Within each chain, order must be preserved
        $this->assertLessThan(
            array_search('ChainB', $order, true),
            array_search('ChainA', $order, true)
        );

        $this->assertLessThan(
            array_search('ChainY', $order, true),
            array_search('ChainX', $order, true)
        );
    }

    public function test_detects_circular_dependencies_two_nodes(): void
    {
        $this->createTestSeeder('CircularA', ['CircularB']);
        $this->createTestSeeder('CircularB', ['CircularA']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $resolver = new IdempotentSeederResolver();
        $resolver->discover($this->testSeedersPath);
    }

    public function test_detects_circular_dependencies_three_nodes(): void
    {
        $this->createTestSeeder('CircularX', ['CircularY']);
        $this->createTestSeeder('CircularY', ['CircularZ']);
        $this->createTestSeeder('CircularZ', ['CircularX']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $resolver = new IdempotentSeederResolver();
        $resolver->discover($this->testSeedersPath);
    }

    public function test_detects_self_referencing_dependency(): void
    {
        $this->createTestSeeder('SelfReferencing', ['SelfReferencing']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $resolver = new IdempotentSeederResolver();
        $resolver->discover($this->testSeedersPath);
    }

    public function test_detects_missing_dependencies(): void
    {
        $this->createTestSeeder('DependentSeeder', ['TestSeeders\\NonExistentSeeder']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/was not discovered|does not exist/');

        $resolver = new IdempotentSeederResolver();
        $resolver->discover($this->testSeedersPath);
    }

    public function test_handles_empty_directory(): void
    {
        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertEmpty($seeders);
    }

    public function test_handles_non_existent_directory(): void
    {
        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover('/path/that/does/not/exist');

        $this->assertEmpty($seeders);
    }

    public function test_handles_glob_patterns(): void
    {
        // Create nested structure
        $subDir1 = $this->testSeedersPath . '/Module1';
        $subDir2 = $this->testSeedersPath . '/Module2';

        mkdir($subDir1, 0755, true);
        mkdir($subDir2, 0755, true);

        $this->createTestSeederInPath($subDir1, 'Module1Seeder', []);
        $this->createTestSeederInPath($subDir2, 'Module2Seeder', []);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath . '/Module*');

        $this->assertCount(2, $seeders);

        // Cleanup
        @unlink($subDir1 . '/Module1Seeder.php');
        @unlink($subDir2 . '/Module2Seeder.php');
        @rmdir($subDir1);
        @rmdir($subDir2);
    }

    public function test_handles_multiple_paths_as_array(): void
    {
        $path1 = $this->testSeedersPath . '/Path1';
        $path2 = $this->testSeedersPath . '/Path2';

        mkdir($path1, 0755, true);
        mkdir($path2, 0755, true);

        $this->createTestSeederInPath($path1, 'Path1Seeder', []);
        $this->createTestSeederInPath($path2, 'Path2Seeder', []);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover([$path1, $path2]);

        $this->assertCount(2, $seeders);

        // Cleanup
        @unlink($path1 . '/Path1Seeder.php');
        @unlink($path2 . '/Path2Seeder.php');
        @rmdir($path1);
        @rmdir($path2);
    }

    public function test_deduplicates_same_seeder_from_multiple_paths(): void
    {
        $this->createTestSeeder('DuplicateSeeder', []);

        $resolver = new IdempotentSeederResolver();
        // Pass the same path twice
        $seeders = $resolver->discover([$this->testSeedersPath, $this->testSeedersPath]);

        $this->assertCount(1, $seeders, 'Should deduplicate the same seeder');
    }

    public function test_validates_successfully_for_valid_seeders(): void
    {
        $this->createTestSeeder('ValidA', []);
        $this->createTestSeeder('ValidB', ['TestSeeders\\ValidA']);

        $resolver = new IdempotentSeederResolver();
        $resolver->discover($this->testSeedersPath);
        $errors = $resolver->validate();

        $this->assertEmpty($errors);
    }

    public function test_validate_reports_missing_dependencies(): void
    {
        $this->createTestSeeder('ValidSeeder', []);
        // Create NonExistent class without AutoSeed attribute so it won't be discovered
        $this->createSeederWithoutAttribute('NonExistentDependency');
        $this->createTestSeeder('InvalidSeeder', ['NonExistentDependency']);

        $resolver = new IdempotentSeederResolver();

        // Resolve will throw, so catch it
        try {
            $resolver->discover($this->testSeedersPath);
        } catch (RuntimeException) {
            // Expected
        }

        $errors = $resolver->validate();
        $this->assertNotEmpty($errors);
        // Should report either a missing dependency or circular dependency error
        $this->assertTrue(
            str_contains($errors[0], 'NonExistentDependency') ||
            str_contains($errors[0], 'Circular dependency') ||
            str_contains($errors[0], 'InvalidSeeder')
        );
    }

    public function test_handles_seeders_with_no_dependencies(): void
    {
        $this->createTestSeeder('Independent1', []);
        $this->createTestSeeder('Independent2', []);
        $this->createTestSeeder('Independent3', []);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(3, $seeders);
    }

    public function test_handles_deep_dependency_chain(): void
    {
        // Create A -> B -> C -> D -> E (5 levels deep)
        $this->createTestSeeder('DeepA', []);
        $this->createTestSeeder('DeepB', ['TestSeeders\\DeepA']);
        $this->createTestSeeder('DeepC', ['TestSeeders\\DeepB']);
        $this->createTestSeeder('DeepD', ['TestSeeders\\DeepC']);
        $this->createTestSeeder('DeepE', ['TestSeeders\\DeepD']);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(5, $seeders);

        $order = array_map(fn ($s) => $s->getShortName(), $seeders);

        // Verify complete chain order
        $this->assertSame('DeepA', $order[0]);
        $this->assertSame('DeepB', $order[1]);
        $this->assertSame('DeepC', $order[2]);
        $this->assertSame('DeepD', $order[3]);
        $this->assertSame('DeepE', $order[4]);
    }

    public function test_ignores_non_php_files(): void
    {
        $this->createTestSeeder('ValidSeeder', []);

        // Create non-PHP files
        file_put_contents($this->testSeedersPath . '/README.md', '# Test');
        file_put_contents($this->testSeedersPath . '/config.json', '{}');

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        $this->assertCount(1, $seeders);
        $this->assertSame('ValidSeeder', $seeders[0]->getShortName());

        // Cleanup
        @unlink($this->testSeedersPath . '/README.md');
        @unlink($this->testSeedersPath . '/config.json');
    }

    public function test_handles_files_without_namespace(): void
    {
        // Create a PHP file without a namespace
        $code = <<<'PHP'
<?php

class NoNamespaceSeeder
{
    public function run(): void {}
}
PHP;

        file_put_contents($this->testSeedersPath . '/NoNamespaceSeeder.php', $code);
        $this->createTestSeeder('ValidSeeder', []);

        $resolver = new IdempotentSeederResolver();
        $seeders = $resolver->discover($this->testSeedersPath);

        // Should only find the valid seeder, ignore the one without namespace
        $this->assertCount(1, $seeders);
        $this->assertSame('ValidSeeder', $seeders[0]->getShortName());
    }

    public function test_reset_state_between_discoveries(): void
    {
        $this->createTestSeeder('FirstRun', []);

        $resolver = new IdempotentSeederResolver();
        $firstRun = $resolver->discover($this->testSeedersPath);

        $this->assertCount(1, $firstRun);

        // Add another seeder
        $this->createTestSeeder('SecondRun', []);

        // Resolve again - should find both
        $secondRun = $resolver->discover($this->testSeedersPath);

        $this->assertCount(2, $secondRun);
    }

    // Helper methods

    private function createTestSeeder(string $name, array $dependencies = []): void
    {
        $this->createTestSeederInPath($this->testSeedersPath, $name, $dependencies);
    }

    private function createTestSeederInPath(string $path, string $name, array $dependencies = []): void
    {
        $dependsOnStr = '';
        if (filled($dependencies)) {
            // Dependencies without namespace are already in TestSeeders namespace
            $deps = array_map(function ($dep) {
                // If dependency contains namespace, prefix with \ to make it absolute
                // Otherwise, just use the class name (will resolve in current namespace)
                if (str_contains($dep, '\\')) {
                    return "\\{$dep}::class";
                }

                return "{$dep}::class";
            }, $dependencies);
            $dependsOnStr = 'dependsOn: [' . implode(', ', $deps) . '],';
        }

        $code = <<<PHP
<?php

namespace TestSeeders;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use Illuminate\Database\Seeder;

#[AutoSeed(
    {$dependsOnStr}
)]
class {$name} extends Seeder implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}
PHP;

        file_put_contents($path . "/{$name}.php", $code);
        require_once $path . "/{$name}.php";
    }

    private function createSeederWithoutAttribute(string $name): void
    {
        $code = <<<PHP
<?php

namespace TestSeeders;

use App\Domains\Core\Contracts\IdempotentSeederInterface;
use Illuminate\Database\Seeder;

class {$name} extends Seeder implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}
PHP;

        file_put_contents($this->testSeedersPath . "/{$name}.php", $code);
        require_once $this->testSeedersPath . "/{$name}.php";
    }

    private function createAbstractSeeder(string $name): void
    {
        $code = <<<PHP
<?php

namespace TestSeeders;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use Illuminate\Database\Seeder;

#[AutoSeed]
abstract class {$name} extends Seeder implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}
PHP;

        file_put_contents($this->testSeedersPath . "/{$name}.php", $code);
        require_once $this->testSeedersPath . "/{$name}.php";
    }
}
