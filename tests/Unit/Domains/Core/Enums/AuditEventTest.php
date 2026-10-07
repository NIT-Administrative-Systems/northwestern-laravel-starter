<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Enums;

use App\Domains\Core\Enums\AuditEvent;
use Filament\Support\Icons\Heroicon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditEvent::class)]
final class AuditEventTest extends TestCase
{
    /**
     * @return \Iterator<string, array{AuditEvent, string}>
     */
    public static function labelProvider(): \Iterator
    {
        yield 'an Eloquent event' => [AuditEvent::Created, 'Created'];
        yield 'a custom event' => [AuditEvent::PermissionsModified, 'Permissions Modified'];
        yield 'an acronym' => [AuditEvent::ServiceClientIpRestrictionsUpdated, 'Service Client IP Restrictions Updated'];
        yield 'another acronym' => [AuditEvent::McpClientRevoked, 'MCP Client Revoked'];
    }

    #[DataProvider('labelProvider')]
    public function test_it_labels_each_event(AuditEvent $event, string $label): void
    {
        $this->assertSame($label, $event->getLabel());
        $this->assertSame($label, AuditEvent::labelFor($event->value));
    }

    public function test_it_colors_what_was_added_removed_and_changed(): void
    {
        $this->assertSame('success', AuditEvent::ApplicationRegistered->getColor());
        $this->assertSame('danger', AuditEvent::PersonalAccessTokenRevoked->getColor());
        $this->assertSame('warning', AuditEvent::ApplicationSecretRegenerated->getColor());
    }

    public function test_every_event_has_an_icon_and_a_color(): void
    {
        foreach (AuditEvent::cases() as $event) {
            $this->assertSame($event->getIcon(), AuditEvent::iconFor($event->value));
            $this->assertContains(AuditEvent::colorFor($event->value), ['success', 'danger', 'warning']);
        }
    }

    // An audit can carry an event the enum doesn't name, such as one recorded before it existed.
    public function test_it_describes_an_event_it_does_not_name(): void
    {
        $this->assertSame('Report Exported', AuditEvent::labelFor('report_exported'));
        $this->assertSame('gray', AuditEvent::colorFor('report_exported'));
        $this->assertSame(Heroicon::OutlinedTag, AuditEvent::iconFor('report_exported'));
    }

    public function test_the_options_list_every_event_by_default(): void
    {
        $options = AuditEvent::options();

        $this->assertCount(count(AuditEvent::cases()), $options);
        $this->assertSame('Application Disconnected', $options['application_disconnected']);
    }

    public function test_the_options_can_be_narrowed_to_some_events(): void
    {
        $this->assertSame(
            ['role_assigned' => 'Role Assigned', 'role_removed' => 'Role Removed'],
            AuditEvent::options(AuditEvent::RoleAssigned, AuditEvent::RoleRemoved),
        );
    }
}
