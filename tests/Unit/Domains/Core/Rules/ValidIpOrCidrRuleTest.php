<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Rules;

use App\Domains\Core\Rules\ValidIpOrCidrRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(ValidIpOrCidrRule::class)]
class ValidIpOrCidrRuleTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_validation_passes_for_valid_values(string $value): void
    {
        $validator = Validator::make(
            ['ip' => $value],
            ['ip' => new ValidIpOrCidrRule()],
        );

        $this->assertTrue($validator->passes(), "Expected '{$value}' to pass validation.");
    }

    #[DataProvider('invalidValues')]
    public function test_validation_fails_for_invalid_values(string $value): void
    {
        $validator = Validator::make(
            ['ip' => $value],
            ['ip' => new ValidIpOrCidrRule()],
        );

        $this->assertFalse($validator->passes(), "Expected '{$value}' to fail validation.");
    }

    public function test_error_message_uses_attribute_name(): void
    {
        $validator = Validator::make(
            ['allowed_ip' => 'not-valid'],
            ['allowed_ip' => new ValidIpOrCidrRule()],
        );

        $validator->passes();

        $this->assertStringContainsString('allowed ip', $validator->errors()->first('allowed_ip'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validValues(): array
    {
        return [
            // IPv4
            'ipv4 loopback' => ['127.0.0.1'],
            'ipv4 private class A' => ['10.0.0.1'],
            'ipv4 private class B' => ['172.16.0.1'],
            'ipv4 private class C' => ['192.168.1.1'],
            'ipv4 public' => ['203.0.113.42'],

            // IPv6
            'ipv6 loopback' => ['::1'],
            'ipv6 link-local' => ['fe80::1'],
            'ipv6 documentation' => ['2001:db8::1'],

            // IPv4 CIDR
            'ipv4 cidr /0' => ['0.0.0.0/0'],
            'ipv4 cidr /8' => ['10.0.0.0/8'],
            'ipv4 cidr /12' => ['172.16.0.0/12'],
            'ipv4 cidr /24' => ['192.168.1.0/24'],
            'ipv4 cidr /32' => ['192.168.1.1/32'],

            // IPv6 CIDR
            'ipv6 cidr /0' => ['::/0'],
            'ipv6 cidr /10' => ['fe80::/10'],
            'ipv6 cidr /32' => ['2001:db8::/32'],
            'ipv6 cidr /128' => ['2001:db8::1/128'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValues(): array
    {
        return [
            'random string' => ['not-an-ip'],
            'hostname' => ['example.com'],
            'ipv4 octets out of range' => ['999.999.999.999'],
            'ipv4 incomplete' => ['192.168.1'],
            'slash only' => ['/'],
            'ipv4 cidr mask too large' => ['192.168.1.0/33'],
            'ipv4 cidr non-numeric mask' => ['10.0.0.0/abc'],
            'ipv4 cidr empty mask' => ['192.168.1.0/'],
            'ipv4 cidr negative mask' => ['192.168.1.0/-1'],
            'ipv6 cidr mask too large' => ['2001:db8::/129'],
            'just a number' => ['42'],
            'ip with port' => ['192.168.1.1:8080'],
        ];
    }
}
