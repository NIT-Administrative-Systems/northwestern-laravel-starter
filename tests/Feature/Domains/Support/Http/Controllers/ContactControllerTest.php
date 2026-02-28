<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Http\Controllers;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Gateway\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Http\Controllers\Support\ContactController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ContactController::class)]
class ContactControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['support.driver' => 'mail']);
        config(['support.mail.to' => 'support@northwestern.edu']);
    }

    public function test_create_returns_contact_form_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('support.contact.create'));

        $response->assertOk();
        $response->assertViewIs('support.contact.create');
    }

    public function test_create_requires_authentication(): void
    {
        $response = $this->get(route('support.contact.create'));

        $response->assertRedirect();
    }

    public function test_create_passes_limited_support_warning_to_view(): void
    {
        config(['support.limited_support_warning' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('support.contact.create'));

        $response->assertViewHas('limitedSupportAlert', true);
    }

    public function test_create_hides_limited_support_warning_when_disabled(): void
    {
        config(['support.limited_support_warning' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('support.contact.create'));

        $response->assertViewHas('limitedSupportAlert', false);
    }

    public function test_store_creates_ticket_and_redirects_with_success(): void
    {
        $this->bindSuccessfulGateway('SUP-1');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('support.contact.store'), [
                'subject' => 'Help with login',
                'details' => 'I cannot sign in to the application.',
            ]);

        $response->assertRedirect(route('support.contact.create'));
        $response->assertSessionHas('status-success');

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'subject' => 'Help with login',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('support.contact.store'), [
            'subject' => 'Test',
            'details' => 'Test details',
        ]);

        $response->assertRedirect();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('support.contact.store'), []);

        $response->assertSessionHasErrors(['subject', 'details']);
    }

    public function test_store_success_message_includes_ticket_number(): void
    {
        $this->bindSuccessfulGateway('SUP-99');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('support.contact.store'), [
                'subject' => 'Test',
                'details' => 'Details',
            ]);

        $response->assertSessionHas('status-success', function (string $message) {
            return str_contains($message, 'SUP-99');
        });
    }

    public function test_store_fallback_message_when_primary_fails_but_fallback_succeeds(): void
    {
        $this->bindFailedGatewayWithFallback();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('support.contact.store'), [
                'subject' => 'Test',
                'details' => 'Details',
            ]);

        $response->assertSessionHas('status-success', function (string $message) {
            return str_contains($message, 'confirmation email');
        });
    }

    public function test_store_error_message_when_both_fail(): void
    {
        $this->bindCompletelyFailedGateway();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('support.contact.store'), [
                'subject' => 'Test',
                'details' => 'Details',
            ]);

        $response->assertSessionHas('status-danger', function (string $message) {
            return str_contains($message, 'unable to submit');
        });
    }

    public function test_store_populates_requester_email_from_user(): void
    {
        $this->bindSuccessfulGateway('SUP-1');
        $user = User::factory()->create(['email' => 'test@northwestern.edu']);

        $this->actingAs($user)
            ->post(route('support.contact.store'), [
                'subject' => 'Test',
                'details' => 'Details',
            ]);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'requester_email' => 'test@northwestern.edu',
        ]);
    }

    protected function bindSuccessfulGateway(string $ticketNumber): void
    {
        $this->app->bind(TicketSystemGateway::class, function () use ($ticketNumber) {
            return new class($ticketNumber) implements TicketSystemGateway
            {
                public function __construct(protected string $ticketNumber)
                {
                }

                public function create(SupportTicket $ticket): CreationResult
                {
                    return new CreationResult(
                        ticketSystemType: TicketSystemEnum::MAIL,
                        creationError: false,
                        ticketNumber: $this->ticketNumber,
                        errorMessage: null,
                    );
                }
            };
        });
    }

    protected function bindFailedGatewayWithFallback(): void
    {
        $this->app->bind(TicketSystemGateway::class, function () {
            return new class implements TicketSystemGateway
            {
                public function create(SupportTicket $ticket): CreationResult
                {
                    return new CreationResult(
                        ticketSystemType: TicketSystemEnum::TEAM_DYNAMIX,
                        creationError: true,
                        ticketNumber: null,
                        errorMessage: 'TDX error',
                    );
                }
            };
        });
    }

    protected function bindCompletelyFailedGateway(): void
    {
        $this->app->bind(TicketSystemGateway::class, function () {
            return new class implements TicketSystemGateway
            {
                public function create(SupportTicket $ticket): CreationResult
                {
                    return new CreationResult(
                        ticketSystemType: TicketSystemEnum::MAIL,
                        creationError: true,
                        ticketNumber: null,
                        errorMessage: 'Mail failed',
                    );
                }
            };
        });
    }
}
