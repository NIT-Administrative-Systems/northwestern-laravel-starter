<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Services\ConfigValidation\SSOValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SSOValidator::class)]
class SSOValidatorTest extends TestCase
{
    public function test_passes_when_entra_id_credentials_are_configured(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('Entra ID', $validator->successMessage());
    }

    public function test_fails_when_entra_id_client_id_is_missing(): void
    {
        config([
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => 'test-client-secret',
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('Entra ID', $validator->errorMessage());
        $this->assertStringContainsString('1 required', $validator->errorMessage());
    }

    public function test_fails_when_entra_id_client_secret_is_missing(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => null,
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('AZURE_CLIENT_SECRET', $validator->hints()[0]);
    }

    public function test_fails_when_both_entra_id_credentials_are_missing(): void
    {
        config([
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => null,
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('2 required', $validator->errorMessage());
    }

    public function test_detects_online_passport_when_websso_api_key_is_set(): void
    {
        config([
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
            'nusoa.sso.apigeeBaseUrl' => 'https://northwestern-prod.apigee.net/agentless-websso',
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('Online Passport', $validator->successMessage());
    }

    public function test_detects_online_passport_when_strategy_is_forgerock_direct(): void
    {
        config([
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
            'nusoa.sso.apigeeBaseUrl' => 'https://example.com',
            'nusoa.sso.strategy' => 'forgerock-direct',
        ]);

        $validator = new SSOValidator();

        $this->assertTrue($validator->validate());
        $this->assertStringContainsString('Online Passport', $validator->successMessage());
    }

    public function test_fails_when_online_passport_api_url_base_is_missing(): void
    {
        config([
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
            'nusoa.sso.apigeeBaseUrl' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertFalse($validator->validate());
        $this->assertStringContainsString('Online Passport', $validator->errorMessage());
        $this->assertStringContainsString('WEBSSO_API_URL_BASE', $validator->hints()[0]);
    }

    public function test_online_passport_does_not_check_entra_id_credentials(): void
    {
        config([
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => null,
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
            'nusoa.sso.apigeeBaseUrl' => 'https://northwestern-prod.apigee.net/agentless-websso',
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertTrue($validator->validate());
    }

    public function test_entra_id_does_not_check_online_passport_credentials(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.apigeeBaseUrl' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();

        $this->assertTrue($validator->validate());
    }

    public function test_hints_include_documentation_reference(): void
    {
        config([
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => null,
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => 'apigee',
        ]);

        $validator = new SSOValidator();
        $validator->validate();

        $hints = $validator->hints();
        $lastHint = end($hints);
        $this->assertStringContainsString('WebSSO / Entra ID', $lastHint);
    }

    public function test_name_returns_expected_value(): void
    {
        $validator = new SSOValidator();

        $this->assertSame('SSO Authentication', $validator->name());
    }
}
