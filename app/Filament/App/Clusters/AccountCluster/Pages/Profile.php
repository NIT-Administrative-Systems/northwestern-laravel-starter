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
                            ->label('Request a Change')
                            ->link()
                            ->url(fn (): string => ContactSupport::getUrl(panel: AppPanelProvider::ID))
                            ->visible(fn (): bool => ! $isNetIdUser && ContactSupport::canAccess()),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('full_name')->label('Name'),
                            TextEntry::make('username')->label('NetID')->visible($isNetIdUser),
                            TextEntry::make('email')->label('Email')->placeholder('None'),
                            TextEntry::make('primary_affiliation')
                                ->label('Primary Affiliation')
                                ->formatStateUsing(fn ($state) => $state?->getLabel())
                                ->placeholder('None')
                                ->visible($isNetIdUser),
                            TextEntry::make('job_titles')
                                ->label($isNetIdUser ? 'Job Titles' : 'Title')
                                ->badge()
                                ->placeholder('None'),
                            TextEntry::make('departments')
                                ->label($isNetIdUser ? 'Departments' : 'Department')
                                ->badge()
                                ->placeholder('None'),
                        ]),
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

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
