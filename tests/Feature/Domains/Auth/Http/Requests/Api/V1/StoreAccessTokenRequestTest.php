<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Requests\Api\V1;

use App\Domains\Auth\Http\Requests\Api\V1\StoreAccessTokenRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StoreAccessTokenRequest::class)]
class StoreAccessTokenRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new StoreAccessTokenRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_validation_passes_with_minimal_valid_data(): void
    {
        $data = ['name' => 'My API Token'];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_complete_valid_data(): void
    {
        $data = [
            'name' => 'My API Token',
            'expires_at' => now()->addDays(30)->timestamp,
            'allowed_ips' => ['192.168.1.1', '10.0.0.0/24', '2001:db8::1', '2001:db8::/32'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_missing_name(): void
    {
        $data = [];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_expires_at_in_past(): void
    {
        $data = [
            'name' => 'My API Token',
            'expires_at' => now()->subDay()->timestamp,
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('expires_at', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_expires_at_today(): void
    {
        $data = [
            'name' => 'My API Token',
            'expires_at' => now()->timestamp,
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('expires_at', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_ipv4_addresses(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['192.168.1.1', '10.0.0.1', '172.16.0.1'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_valid_ipv6_addresses(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['2001:db8::1', 'fe80::1', '::1'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_valid_ipv4_cidr_ranges(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['192.168.1.0/24', '10.0.0.0/8', '172.16.0.0/12'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_valid_ipv6_cidr_ranges(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['2001:db8::/32', 'fe80::/10', '::/0'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_invalid_ip_addresses(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['not-an-ip', '999.999.999.999', '192.168.1'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('allowed_ips.0', $validator->errors()->toArray());
        $this->assertArrayHasKey('allowed_ips.1', $validator->errors()->toArray());
        $this->assertArrayHasKey('allowed_ips.2', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_cidr_ranges(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['192.168.1.0/33', '10.0.0.0/abc', '2001:db8::/129'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('allowed_ips.0', $validator->errors()->toArray());
        $this->assertArrayHasKey('allowed_ips.1', $validator->errors()->toArray());
        $this->assertArrayHasKey('allowed_ips.2', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_cidr_missing_mask(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['192.168.1.0/'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('allowed_ips.0', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_null_optional_fields(): void
    {
        $data = [
            'name' => 'My API Token',
            'expires_at' => null,
            'allowed_ips' => null,
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_empty_allowed_ips_array(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => [],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_when_allowed_ips_is_not_array(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => 'not-an-array',
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('allowed_ips', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_maximum_ipv4_cidr_mask(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['192.168.1.1/32'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_maximum_ipv6_cidr_mask(): void
    {
        $data = [
            'name' => 'My API Token',
            'allowed_ips' => ['2001:db8::1/128'],
        ];
        $request = new StoreAccessTokenRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }
}
