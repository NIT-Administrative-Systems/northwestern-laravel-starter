<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Services\ConfigValidation\DirectorySearchValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DirectorySearchValidator::class)]
final class DirectorySearchValidatorTest extends TestCase
{
    public function test_should_always_run(): void
    {
        $validator = new DirectorySearchValidator();

        $this->assertTrue($validator->shouldRun());
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
