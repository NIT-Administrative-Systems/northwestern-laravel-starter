<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domains\Support\Actions\CreateSupportTicket;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

/**
 * Sends a support request to the configured ticket system, falling back to email.
 * Available when `support.enabled` is on.
 *
 * @property-read Schema $form
 */
class ContactSupport extends Page
{
    protected static ?string $title = 'Contact Support';

    protected static ?string $slug = 'support/contact';

    // Reached from the Help menu in the top bar, not the sidebar.
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) config('support.enabled');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->label('Subject')
                    ->placeholder('I need help with…')
                    ->required()
                    ->maxLength(200)
                    ->autocomplete(false),
                Textarea::make('details')
                    ->label('Details')
                    ->placeholder('Describe the problem or your idea…')
                    ->helperText('Include what you were trying to do, what happened, and any steps to reproduce the problem.')
                    ->required()
                    ->maxLength(10000)
                    ->rows(6),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
                Callout::make()
                    ->description(new HtmlString(
                        '<strong>Limited support.</strong> Support for this environment is limited, and this form may send an email instead of creating a ticket. '
                        . 'If you\'re testing for a project, contact the project team instead.'
                    ))
                    ->warning()
                    ->visible((bool) config('support.limited_support_warning')),
                Section::make('Need Help or Have a Question?')
                    ->description('Your request goes to the team that supports ' . config('app.name') . '. You\'ll get a confirmation email, and someone will follow up as soon as they can.')
                    ->schema([
                        Text::make('Feature requests and feedback are welcome too.'),
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('form')
                            ->livewireSubmitHandler('send')
                            ->footer([
                                Actions::make([
                                    Action::make('send')
                                        ->label('Send')
                                        ->icon(Heroicon::OutlinedPaperAirplane)
                                        ->submit('send'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function send(SupportTicketRepository $repository, CreateSupportTicket $createTicket): void
    {
        abort_unless(static::canAccess(), 404);

        $data = $this->form->getState();

        if ($this->tooManyRequests()) {
            Notification::make()
                ->title('Too Many Requests')
                ->body('You\'ve sent several support requests recently. Try again later.')
                ->danger()
                ->send();

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $ticket = $createTicket($repository->create($user, new SupportTicket($data)));

        if ($ticket->wasPostedSuccessfully() || $ticket->fallback_sent_at !== null) {
            Notification::make()
                ->title('Request Sent')
                ->body($ticket->wasPostedSuccessfully()
                    ? "Your reference number is {$ticket->ticket_number}. You'll get a confirmation email shortly."
                    : 'You\'ll get a confirmation email shortly.')
                ->success()
                ->send();

            $this->form->fill();

            return;
        }

        Notification::make()
            ->title('Request Not Sent')
            ->body('We couldn\'t send your request. Try again later.')
            ->danger()
            ->send();
    }

    /**
     * Apply the `support:contact` rate limits, which were route middleware for the
     * previous controller-based form. Livewire actions don't pass through route middleware.
     */
    private function tooManyRequests(): bool
    {
        /** @var list<Limit> $limits */
        $limits = Arr::wrap(RateLimiter::limiter('support:contact')(request()));

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts('support:contact:' . $limit->key, $limit->maxAttempts)) {
                return true;
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit('support:contact:' . $limit->key, $limit->decaySeconds);
        }

        return false;
    }
}
