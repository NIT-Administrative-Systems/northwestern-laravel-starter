<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\SupportTickets;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Filament\Administration\Concerns\AssertsLinkedRows;
use Tests\TestCase;

#[CoversNothing]
final class SupportTicketResourceTest extends TestCase
{
    use AssertsLinkedRows;

    protected function setUp(): void
    {
        parent::setUp();

        config(['support.enabled' => true]);
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    public function test_a_ticket_that_never_reached_a_gateway_has_no_empty_links(): void
    {
        $ticket = SupportTicket::factory()->create([
            'requester_email' => null,
            'ticketing_system' => null,
            'ticket_number' => null,
            'error_message' => null,
        ]);
        $ticket->user->delete();
        $this->actingAs($this->administrator());

        $this->assertEveryEmptyCellHasPlaceholder(Livewire::test(ListSupportTickets::class), $ticket);
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewSupportTickets);

        return $administrator;
    }
}
