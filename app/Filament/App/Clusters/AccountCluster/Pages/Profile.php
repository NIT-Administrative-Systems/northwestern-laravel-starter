<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster;
use App\Filament\App\Pages\ContactSupport;
use App\Providers\Filament\AppPanelProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Read-only: a NetID user's details belong to the Northwestern Directory, and an email
 * user's to the administrator who created the account.
 */
class Profile extends Page
{
    /** How many sign-ins the page lists. */
    public const int RECENT_SIGN_INS = 10;

    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $title = 'Profile';

    protected static ?string $slug = 'profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 1;

    public function content(Schema $schema): Schema
    {
        $user = $this->user();
        $isNetIdUser = $user->auth_type === AuthType::SSO;

        return $schema
            ->record($user)
            ->components([
                Section::make('Details')
                    ->description($isNetIdUser
                        ? 'From the Northwestern Directory, refreshed each time you sign in.'
                        : 'Entered by an administrator when your account was created.')
                    ->afterHeader([
                        Action::make('contactSupport')
                            ->label('Request a change')
                            ->link()
                            ->url(fn (): string => ContactSupport::getUrl(panel: AppPanelProvider::ID))
                            ->visible(fn (): bool => ! $isNetIdUser && ContactSupport::canAccess()),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('full_name')->label('Name'),
                            TextEntry::make('username')->label('NetID')->visible($isNetIdUser),
                            TextEntry::make('email')->label('Email address')->placeholder('None'),
                            TextEntry::make('primary_affiliation')
                                ->label('Primary affiliation')
                                ->formatStateUsing(fn ($state) => $state?->getLabel())
                                ->placeholder('None')
                                ->visible($isNetIdUser),
                            TextEntry::make('job_titles')
                                ->label($isNetIdUser ? 'Job titles' : 'Title')
                                ->badge()
                                ->placeholder('None'),
                            TextEntry::make('departments')
                                ->label($isNetIdUser ? 'Departments' : 'Department')
                                ->badge()
                                ->placeholder('None'),
                        ]),
                    ]),

                Section::make('Sign-in')
                    ->description($this->signInDescription())
                    ->schema([
                        TextEntry::make('sign_in_method')
                            ->label('Method')
                            ->state(match ($user->auth_type) {
                                AuthType::SSO => 'NetID',
                                AuthType::Local => 'Email verification code',
                                default => $user->auth_type->getLabel(),
                            }),
                        RepeatableEntry::make('recent_sign_ins')
                            ->label('Recent sign-ins')
                            ->state(fn (): array => $user->login_records()
                                ->latest('logged_in_at')
                                ->limit(self::RECENT_SIGN_INS)
                                ->get(['logged_in_at', 'ip_address'])
                                ->all())
                            ->table([
                                TableColumn::make('Signed in'),
                                TableColumn::make('IP address'),
                            ])
                            ->schema([
                                // Labels match the column headers: narrow screens stack each row with them.
                                TextEntry::make('logged_in_at')->label('Signed in')->dateTime(),
                                TextEntry::make('ip_address')->label('IP address')->placeholder('Unknown'),
                            ])
                            ->placeholder('No sign-ins recorded yet.'),
                    ]),

                Section::make('Roles')
                    ->description('Roles decide what you can do in this application. An administrator assigns them.')
                    ->schema([
                        TextEntry::make('roles')
                            ->hiddenLabel()
                            ->state($user->non_default_roles->pluck('name')->sort()->values()->all())
                            ->badge()
                            ->placeholder('None beyond standard access.'),
                    ]),
            ]);
    }

    private function signInDescription(): string
    {
        $description = 'The last ' . self::RECENT_SIGN_INS . ' sign-ins. Contact support if you don\'t recognize one.';

        $retentionDays = config('platform.retention.login_records');

        return filled($retentionDays)
            ? "{$description} Records are kept for {$retentionDays} days."
            : $description;
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
