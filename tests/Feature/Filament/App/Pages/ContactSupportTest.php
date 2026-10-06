<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\ContactSupport;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ContactSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        config(['support.enabled' => true]);
        config(['support.driver' => 'mail']);
        config(['support.mail.to' => 'support@northwestern.edu']);
    }

    public function test_page_renders_in_the_app_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/support/contact')
            ->assertOk()
            ->assertSee('Need Help or Have a Question?');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/app/support/contact')->assertRedirect('/app/login');
    }

    public function test_page_is_unavailable_when_support_is_disabled(): void
    {
        config(['support.enabled' => false]);

        $this->actingAs(User::factory()->create())
            ->get('/app/support/contact')
            ->assertForbidden();
    }

    public function test_shows_the_limited_support_warning_only_when_enabled(): void
    {
        $this->actingAs(User::factory()->create());

        config(['support.limited_support_warning' => true]);
        Livewire::test(ContactSupport::class)->assertSee('Limited support');

        config(['support.limited_support_warning' => false]);
        Livewire::test(ContactSupport::class)->assertDontSee('Limited support');
    }

    public function test_sending_creates_a_ticket_and_reports_its_number(): void
    {
        $this->bindGateway(new CreationResult(TicketSystem::Mail, creationError: false, ticketNumber: 'SUP-1', errorMessage: null));
        $user = User::factory()->create(['email' => 'test@northwestern.edu']);
        $this->actingAs($user);

        Livewire::test(ContactSupport::class)
            ->fillForm(['subject' => 'Help with login', 'details' => 'I cannot sign in to the application.'])
            ->call('send')
            ->assertHasNoFormErrors()
            ->assertNotified('Request Sent')
            ->assertSchemaStateSet(['subject' => null, 'details' => null]);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'subject' => 'Help with login',
            'requester_email' => 'test@northwestern.edu',
        ]);
    }

    public function test_reports_success_when_only_the_email_fallback_was_sent(): void
    {
        $this->bindGateway(new CreationResult(TicketSystem::TeamDynamix, creationError: true, ticketNumber: null, errorMessage: 'TDX error'));
        $this->actingAs(User::factory()->create());

        Livewire::test(ContactSupport::class)
            ->fillForm(['subject' => 'Test', 'details' => 'Details'])
            ->call('send')
            ->assertNotified('Request Sent');
    }

    public function test_reports_failure_when_nothing_was_sent(): void
    {
        $this->bindGateway(new CreationResult(TicketSystem::Mail, creationError: true, ticketNumber: null, errorMessage: 'Mail failed'));
        $this->actingAs(User::factory()->create());

        Livewire::test(ContactSupport::class)
            ->fillForm(['subject' => 'Test', 'details' => 'Details'])
            ->call('send')
            ->assertNotified('Request Not Sent');
    }

    public function test_subject_and_details_are_required_and_limited(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ContactSupport::class)
            ->fillForm(['subject' => '', 'details' => ''])
            ->call('send')
            ->assertHasFormErrors(['subject' => 'required', 'details' => 'required']);

        Livewire::test(ContactSupport::class)
            ->fillForm(['subject' => str_repeat('a', 201), 'details' => str_repeat('a', 10001)])
            ->call('send')
            ->assertHasFormErrors(['subject' => 'max', 'details' => 'max']);

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_applies_the_support_contact_rate_limit(): void
    {
        config(['rate-limiting.support.contact.per_minute' => 1]);
        $this->bindGateway(new CreationResult(TicketSystem::Mail, creationError: false, ticketNumber: 'SUP-1', errorMessage: null));
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(ContactSupport::class);

        $page->fillForm(['subject' => 'First', 'details' => 'Details'])->call('send');
        $page->fillForm(['subject' => 'Second', 'details' => 'Details'])
            ->call('send')
            ->assertNotified('Too Many Requests');

        $this->assertSame(1, SupportTicket::count());
    }

    private function bindGateway(CreationResult $result): void
    {
        $this->app->bind(TicketSystemGateway::class, fn () => new readonly class($result) implements TicketSystemGateway
        {
            public function __construct(private CreationResult $result)
            {
            }

            public function create(SupportTicket $ticket): CreationResult
            {
                return $this->result;
            }
        });
    }
}
