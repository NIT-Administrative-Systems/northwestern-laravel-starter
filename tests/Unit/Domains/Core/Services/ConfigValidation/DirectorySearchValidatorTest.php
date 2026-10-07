<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Services\ConfigValidation\DirectorySearchValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DirectorySearchValidator::class)]
final class DirectorySearchValidatorTest extends TestCase
{
    public function test_runs_outside_local_even_without_a_key(): void
    {
        config(['nusoa.directorySearch.apiKey' => null]);

        $this->assertTrue((new DirectorySearchValidator())->shouldRun());
    }

    // Local environments seed their users without Directory Search, so a missing key isn't a failure there.
    public function test_is_skipped_locally_only_when_no_key_is_set(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        config(['nusoa.directorySearch.apiKey' => null]);
        $this->assertFalse((new DirectorySearchValidator())->shouldRun());

        config(['nusoa.directorySearch.apiKey' => 'test-api-key']);
        $this->assertTrue((new DirectorySearchValidator())->shouldRun());
    }

    public function test_passes_when_api_key_is_configured(): void
    {
        config(['nusoa.directorySearch.apiKey' => 'test-api-key']);

        $validator = new DirectorySearchValidator();

        $this->assertTrue($validator->validate());
        $this->assertSame('Directory Search API key is configured', $validator->successMessage());
    }

    public function test_fails_when_api_key_is_null(): void
    {
        config(['nusoa.directorySearch.apiKey' => null]);

        $validator = new DirectorySearchValidator();

        $this->assertFalse($validator->validate());
        $this->assertSame('Directory Search API key is not set', $validator->errorMessage());
    }

    public function test_fails_when_api_key_is_empty_string(): void
    {
        config(['nusoa.directorySearch.apiKey' => '']);

        $validator = new DirectorySearchValidator();

        $this->assertFalse($validator->validate());
    }

    public function test_hints_include_env_variable_name(): void
    {
        $validator = new DirectorySearchValidator();
        $validator->validate();

        $this->assertStringContainsString('DIRECTORY_SEARCH_API_KEY', $validator->hints()[0]);
    }

    public function test_hints_include_api_service_registry_reference(): void
    {
        $validator = new DirectorySearchValidator();
        $validator->validate();

        $this->assertStringContainsString('API Service Registry', $validator->hints()[1]);
    }
}
