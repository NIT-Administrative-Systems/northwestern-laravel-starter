<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways;

use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixGateway;
use App\Domains\Support\Gateways\TicketSystemGatewayFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TicketSystemGatewayFactory::class)]
class TicketSystemGatewayFactoryTest extends TestCase
{
    public function test_default_returns_mail_gateway_when_driver_is_mail(): void
    {
        config(['support.driver' => 'mail']);

        $gateway = $this->factory()->default();

        $this->assertInstanceOf(MailGateway::class, $gateway);
    }

    public function test_default_returns_team_dynamix_gateway_when_driver_is_team_dynamix(): void
    {
        config(['support.driver' => 'team-dynamix']);

        $gateway = $this->factory()->default();

        $this->assertInstanceOf(TeamDynamixGateway::class, $gateway);
    }

    public function test_default_throws_for_unsupported_driver(): void
    {
        config(['support.driver' => 'jira']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported support driver: [jira]');

        $this->factory()->default();
    }

    public function test_default_exception_message_lists_all_supported_drivers(): void
    {
        config(['support.driver' => 'invalid']);

        try {
            $this->factory()->default();
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            foreach (TicketSystemEnum::cases() as $case) {
                $this->assertStringContainsString($case->value, $e->getMessage());
            }
        }
    }

    public function test_fallback_returns_mail_gateway_with_fallback_strategy(): void
    {
        $gateway = $this->factory()->fallback();

        $reflection = new \ReflectionProperty($gateway, 'isFallbackStrategy');
        $this->assertTrue($reflection->getValue($gateway));
    }

    public function test_make_resolves_gateway_for_each_type(): void
    {
        $factory = $this->factory();

        $this->assertInstanceOf(MailGateway::class, $factory->make(TicketSystemEnum::MAIL));
        $this->assertInstanceOf(TeamDynamixGateway::class, $factory->make(TicketSystemEnum::TEAM_DYNAMIX));
    }

    protected function factory(): TicketSystemGatewayFactory
    {
        return new TicketSystemGatewayFactory();
    }
}
