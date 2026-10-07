<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixGateway;
use App\Providers\SupportServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(SupportServiceProvider::class)]
final class SupportServiceProviderTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, class-string<TicketSystemGateway>}>
     */
    public static function driverProvider(): \Iterator
    {
        yield 'mail' => ['mail', MailGateway::class];
        yield 'TeamDynamix' => ['team-dynamix', TeamDynamixGateway::class];
    }

    /**
     * @param  class-string<TicketSystemGateway>  $gateway
     */
    #[DataProvider('driverProvider')]
    public function test_the_driver_chooses_the_ticket_system(string $driver, string $gateway): void
    {
        $this->assertInstanceOf($gateway, $this->gatewayFor(['support.enabled' => true, 'support.driver' => $driver]));
    }

    public function test_an_unsupported_driver_names_the_supported_ones(): void
    {
        try {
            $this->gatewayFor(['support.enabled' => true, 'support.driver' => 'jira']);
            $this->fail('An unsupported driver was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsupported support driver: [jira]', $e->getMessage());

            foreach (TicketSystem::cases() as $system) {
                $this->assertStringContainsString($system->value, $e->getMessage());
            }
        }
    }

    public function test_nothing_is_bound_while_support_is_disabled(): void
    {
        $this->app->offsetUnset(TicketSystemGateway::class);
        config(['support.enabled' => false]);

        new SupportServiceProvider($this->app)->register();

        $this->assertFalse($this->app->bound(TicketSystemGateway::class));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function gatewayFor(array $config): TicketSystemGateway
    {
        config($config);
        new SupportServiceProvider($this->app)->register();

        return resolve(TicketSystemGateway::class);
    }
}
