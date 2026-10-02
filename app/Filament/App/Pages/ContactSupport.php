<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domains\Support\Actions\CreateSupportTicket;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;
use App\Domains\User\Models\User;
use App\Filament\Navigation\AppNavGroup;
use BackedEnum;
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
use UnitEnum;

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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = AppNavGroup::Help;

    protected static ?int $navigationSort = 2;

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
                    ->placeholder('I need help with...')
                    ->required()
                    ->maxLength(200)
                    ->autocomplete(false),
                Textarea::make('details')
                    ->label('Details')
                    ->placeholder('Describe the issue or your idea...')
                    ->helperText('Please include relevant details such as what you were trying to do, what happened, and any steps to reproduce the issue.')
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
                Callout::make('Limited support')
                    ->description('Limited support for this environment is available. The contact form may behave differently by sending emails instead of creating tickets in the IT ticketing system. If you are performing testing for a project, please reach out to the project team instead of using this form.')
                    ->warning()
                    ->visible((bool) config('support.limited_support_warning')),
                Section::make('Need help or have a question?')
                    ->description('Submitting this form sends your request directly to the Northwestern IT support team. You will receive a confirmation email, and a team member will follow up with you as soon as possible.')
                    ->schema([
                        Text::make('We also welcome enhancement requests and feedback. New functionality is at the discretion of the platform advisory group, and approved enhancements are prioritized against other requests.'),
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
                ->title('You have sent too many support requests. Please try again later.')
                ->danger()
                ->send();

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $ticket = $createTicket($repository->create($user, new SupportTicket($data)));

        if ($ticket->wasPostedSuccessfully() || $ticket->fallback_sent_at !== null) {
            Notification::make()
                ->title($ticket->wasPostedSuccessfully()
                    ? "Your support request has been submitted ({$ticket->ticket_number})."
                    : 'Your request has been submitted.')
                ->body('You will receive a confirmation email shortly.')
                ->success()
                ->send();

            $this->form->fill();

            return;
        }

        Notification::make()
            ->title('We were unable to submit your request. Please try again later.')
            ->danger()
            ->send();
    }

    /**
     * Apply the `support:contact` rate limits, which were route middleware for the
     * Bootstrap form. Livewire actions don't pass through route middleware.
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
