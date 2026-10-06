<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Actions\UpdateUserPreferences;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * The user's timezone and {@see UserPreferences}. An administrator impersonating the user
 * sees them read-only: saving is refused, so impersonation never changes someone's settings.
 *
 * @property-read Schema $form
 */
class Preferences extends Page
{
    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $title = 'Preferences';

    protected static ?string $slug = 'preferences';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = $this->user();

        $this->form->fill([
            'timezone' => $user->timezone,
            ...$user->preferences->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Date and Time')
                    ->schema([
                        Select::make('timezone')
                            ->label('Timezone')
                            ->helperText('Dates and times in this application are shown in this timezone.')
                            ->options($this->timezoneOptions())
                            ->in(DateTimeZone::listIdentifiers())
                            ->searchable()
                            ->required(),
                    ]),
                Section::make('Email')
                    ->schema([
                        Toggle::make('emailAnnouncements')
                            ->label('Email Me Announcements')
                            ->helperText('When an announcement is sent to you. You\'ll see it in your notifications either way.'),
                        Toggle::make('emailWhenApplicationConnects')
                            ->label('Email Me When an Application Connects to My Account')
                            ->helperText('You\'ll see it in your notifications either way.')
                            ->visible(fn (): bool => (bool) config('api.enabled') || (bool) config('mcp.enabled')),
                        Toggle::make('emailBeforeAccessTokensExpire')
                            ->label('Email Me Before My Personal Access Tokens Expire')
                            ->visible(fn (): bool => (bool) config('api.enabled') && $this->user()->can(SystemPermission::CreatePersonalAccessTokens)),
                    ]),
            ])
            ->disabled($this->isImpersonating())
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
                Callout::make()
                    ->description(new HtmlString('<strong>You\'re impersonating this person.</strong> You can see their preferences, but you can\'t change them.'))
                    ->warning()
                    ->visible($this->isImpersonating()),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->submit('save')
                                ->hidden($this->isImpersonating()),
                        ]),
                    ]),
            ]);
    }

    public function save(UpdateUserPreferences $updatePreferences): void
    {
        abort_if($this->isImpersonating(), 403);

        $data = $this->form->getState();
        $user = $this->user();

        $updatePreferences($user, $data['timezone'], $user->preferences->with(Arr::except($data, ['timezone'])));

        Notification::make()
            ->title('Preferences Saved')
            ->success()
            ->send();
    }

    /**
     * Timezone identifiers grouped by region: "America" => ["America/Chicago" => "Chicago (America)"].
     * The label repeats the region because the selected value is shown without its group.
     *
     * @return array<string, array<string, string>>
     */
    private function timezoneOptions(): array
    {
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            if (! str_contains($identifier, '/')) {
                $options[$identifier][$identifier] = $identifier;

                continue;
            }

            [$region, $place] = explode('/', $identifier, 2);

            $options[$region][$identifier] = str_replace(['_', '/'], [' ', ' / '], $place) . " ({$region})";
        }

        return $options;
    }

    private function isImpersonating(): bool
    {
        return $this->user()->isImpersonated();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
