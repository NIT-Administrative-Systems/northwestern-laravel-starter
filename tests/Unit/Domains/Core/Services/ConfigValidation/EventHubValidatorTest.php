<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Services\ConfigValidation\EventHubValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EventHubValidator::class)]
class EventHubValidatorTest extends TestCase
{
    public function test_passes_when_mock_mode_is_enabled(): void
    {
        config(['nusoa.eventHub.mock' => true]);

        $validator = new EventHubValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('mock mode', $validator->successMessage());
    }

    public function test_passes_when_all_credentials_are_configured(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://northwestern-dev.apigee.net',
            'nusoa.eventHub.apiKey' => 'test-api-key',
            'nusoa.eventHub.hmacVerificationSharedSecret' => 'test-secret',
        ]);

        $validator = new EventHubValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('northwestern-dev.apigee.net', $validator->successMessage());
    }

    public function test_skips_when_no_credentials_are_configured(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => null,
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => null,
        ]);

        $validator = new EventHubValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('not configured', $validator->successMessage());
        $this->assertStringContainsString('optional', $validator->successMessage());
    }

    public function test_fails_when_partially_configured(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://northwestern-dev.apigee.net',
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => null,
        ]);

        $validator = new EventHubValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('partial configuration', $validator->errorMessage());
        $this->assertStringContainsString('2 required', $validator->errorMessage());
    }

    public function test_fails_when_only_api_key_is_missing(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://northwestern-dev.apigee.net',
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => 'test-secret',
        ]);

        $validator = new EventHubValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('1 required', $validator->errorMessage());
    }

    public function test_hints_suggest_missing_variables(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://northwestern-dev.apigee.net',
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => null,
        ]);

        $validator = new EventHubValidator();
        $validator->validate();

        $hints = $validator->hints();

        $this->assertStringContainsString('EVENT_HUB_API_KEY', $hints[0]);
        $this->assertStringContainsString('EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET', $hints[1]);
    }

    public function test_hints_suggest_mock_mode(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://example.com',
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => null,
        ]);

        $validator = new EventHubValidator();
        $validator->validate();

        $hints = $validator->hints();

        $this->assertTrue(
            collect($hints)->contains(fn (string $hint) => str_contains($hint, 'EVENT_HUB_MOCK_ENABLED=true'))
        );
    }

    public function test_hints_suggest_removing_variables_when_not_needed(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => 'https://example.com',
            'nusoa.eventHub.apiKey' => null,
            'nusoa.eventHub.hmacVerificationSharedSecret' => null,
        ]);

        $validator = new EventHubValidator();
        $validator->validate();

        $hints = $validator->hints();

        $this->assertTrue(
            collect($hints)->contains(fn (string $hint) => str_contains($hint, 'remove all EVENT_HUB_'))
        );
    }

    public function test_skips_when_all_credentials_are_empty_strings(): void
    {
        config([
            'nusoa.eventHub.mock' => false,
            'nusoa.eventHub.baseUrl' => '',
            'nusoa.eventHub.apiKey' => '',
            'nusoa.eventHub.hmacVerificationSharedSecret' => '',
        ]);

        $validator = new EventHubValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('not configured', $validator->successMessage());
    }

    public function test_name_returns_expected_value(): void
    {
        $validator = new EventHubValidator();

        $this->assertSame('EventHub', $validator->name());
    }
}
