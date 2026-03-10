<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services;

use App\Domains\Core\Attributes\StarterValidator;
use App\Domains\Core\Contracts\ConfigValidator;
use App\Domains\Core\Services\ConfigValidatorResolver;
use App\Domains\Core\ValueObjects\ResolvedValidator;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ConfigValidatorResolver::class)]
#[CoversClass(ResolvedValidator::class)]
#[CoversClass(StarterValidator::class)]
class ConfigValidatorResolverTest extends TestCase
{
    public function test_discovers_all_starter_validators_in_alphabetical_order(): void
    {
        $resolver = new ConfigValidatorResolver();
        $resolved = $resolver->discover();

        $this->assertNotEmpty($resolved);
        $this->assertContainsOnlyInstancesOf(ResolvedValidator::class, $resolved);

        $descriptions = array_map(fn (ResolvedValidator $r) => $r->description, $resolved);

        $this->assertSame([
            'Application Key',
            'Database Connection',
            'Directory Search',
            'EventHub',
            'Queue Connection',
            'S3 Storage',
            'SSO Authentication',
        ], $descriptions);
    }

    public function test_resolved_validators_have_descriptions(): void
    {
        $resolver = new ConfigValidatorResolver();
        $resolved = $resolver->discover();

        foreach ($resolved as $item) {
            $this->assertNotEmpty($item->description);
        }
    }

    public function test_returns_empty_array_for_nonexistent_path(): void
    {
        $resolver = new ConfigValidatorResolver();
        $validators = $resolver->discover('/nonexistent/path');

        $this->assertSame([], $validators);
    }

    public function test_returns_empty_array_for_directory_with_no_validators(): void
    {
        $resolver = new ConfigValidatorResolver();
        $validators = $resolver->discover(app_path('Http/Controllers'));

        $this->assertSame([], $validators);
    }

    public function test_supports_glob_patterns(): void
    {
        $resolver = new ConfigValidatorResolver();
        $resolved = $resolver->discover(app_path('Domains/**/Services/ConfigValidation'));

        $this->assertNotEmpty($resolved);
        $this->assertContainsOnlyInstancesOf(ResolvedValidator::class, $resolved);
    }

    public function test_supports_array_of_paths(): void
    {
        $resolver = new ConfigValidatorResolver();
        $resolved = $resolver->discover([
            app_path('Domains/Core/Services/ConfigValidation'),
        ]);

        $this->assertNotEmpty($resolved);
    }

    public function test_deduplicates_validators_from_overlapping_paths(): void
    {
        $resolver = new ConfigValidatorResolver();
        $resolved = $resolver->discover([
            app_path('Domains/Core/Services/ConfigValidation'),
            app_path('Domains/Core/Services/ConfigValidation'),
        ]);

        $classes = array_map(fn (ResolvedValidator $r) => $r->validator::class, $resolved);

        $this->assertSame($classes, array_unique($classes));
    }

    public function test_ignores_php_files_without_namespace(): void
    {
        $tmpDir = sys_get_temp_dir() . '/starter-validator-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . '/NoNamespace.php', "<?php\nclass NoNamespace {}\n");

        try {
            $resolver = new ConfigValidatorResolver();
            $validators = $resolver->discover($tmpDir);

            $this->assertSame([], $validators);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    public function test_ignores_non_php_files(): void
    {
        $tmpDir = sys_get_temp_dir() . '/starter-validator-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . '/readme.txt', 'not a php file');

        try {
            $resolver = new ConfigValidatorResolver();
            $validators = $resolver->discover($tmpDir);

            $this->assertSame([], $validators);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    public function test_ignores_config_validator_without_attribute(): void
    {
        $tmpDir = sys_get_temp_dir() . '/starter-validator-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        // Create a file whose namespace+class resolves to an existing class
        // that implements ConfigValidator but does NOT have #[StarterValidator].
        // We use a fixture class defined below in this test file.
        file_put_contents(
            $tmpDir . '/UnattributedValidator.php',
            "<?php\nnamespace Tests\\Unit\\Domains\\Core\\Services;\nclass UnattributedValidator {}\n"
        );

        try {
            $resolver = new ConfigValidatorResolver();
            $validators = $resolver->discover($tmpDir);

            $this->assertSame([], $validators);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }
}

/**
 * Test fixture: implements ConfigValidator but has no #[StarterValidator] attribute.
 */
class UnattributedValidator implements ConfigValidator
{
    public function shouldRun(): bool
    {
        return true;
    }

    public function validate(): bool
    {
        return true;
    }

    public function successMessage(): string
    {
        return '';
    }

    public function errorMessage(): string
    {
        return '';
    }

    public function hints(): array
    {
        return [];
    }
}
