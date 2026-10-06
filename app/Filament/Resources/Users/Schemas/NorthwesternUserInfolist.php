<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Support\NetIdStatus;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class NorthwesternUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make()
                ->columns([
                    'default' => 1,
                    'lg' => 3,
                ])
                ->columnSpanFull()
                ->schema([
                    Grid::make(1)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 2,
                        ])
                        ->schema([
                            Section::make('User Overview')
                                ->icon(Heroicon::OutlinedIdentification)
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'xl' => 12,
                                    ])
                                        ->schema([
                                            ImageEntry::make('wildcard_photo')
                                                ->visible(fn (User $record) => config('platform.wildcard_photo_sync'))
                                                ->hiddenLabel()
                                                ->square()
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'xl' => fn () => config('platform.wildcard_photo_sync') ? 2 : 0,
                                                ])
                                                ->getStateUsing(function (User $record): string {
                                                    if (! config('platform.wildcard_photo_sync')) {
                                                        return '';
                                                    }

                                                    if (filled($record->wildcard_photo_s3_key)) {
                                                        return route('users.wildcard-photo', [
                                                            'user' => $record,
                                                            'c' => md5($record->wildcard_photo_last_synced_at->toString()),
                                                        ]);
                                                    }

                                                    return route('users.wildcard-photo', $record);
                                                })
                                                ->defaultImageUrl(asset('images/default-profile-photo.svg'))
                                                ->extraImgAttributes([
                                                    'loading' => 'lazy',
                                                ]),

                                            Grid::make()
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'xl' => fn () => config('platform.wildcard_photo_sync') ? 10 : 12,
                                                ])
                                                ->columns([
                                                    'default' => 1,
                                                    'xl' => 2,
                                                ])
                                                ->schema([
                                                    TextEntry::make('full_name')
                                                        ->size(TextSize::Large)
                                                        ->weight(FontWeight::Medium)
                                                        ->color('primary')
                                                        ->label('Name'),

                                                    TextEntry::make('username')
                                                        ->label('NetID')
                                                        ->fontFamily(FontFamily::Mono)
                                                        ->copyable(),

                                                    TextEntry::make('email')
                                                        ->label('Email')
                                                        ->url(fn ($state) => filled($state) ? 'mailto:' . $state : null)
                                                        ->openUrlInNewTab()
                                                        ->placeholder('N/A')
                                                        ->columnSpanFull()
                                                        ->columnSpan([
                                                            'default' => 1,
                                                        ]),

                                                    TextEntry::make('employee_id')
                                                        ->label('Employee ID')
                                                        ->icon(Heroicon::OutlinedHashtag)
                                                        ->copyable(),

                                                    TextEntry::make('phone')
                                                        ->label('Phone Number')
                                                        ->url(fn ($state) => filled($state) ? 'tel:' . preg_replace('/\D+/', '', (string) $state) : null)
                                                        ->openUrlInNewTab()
                                                        ->placeholder('N/A'),

                                                    TextEntry::make('hr_employee_id')
                                                        ->label('myHR Employee ID')
                                                        ->icon(Heroicon::OutlinedHashtag)
                                                        ->visible(fn ($record) => filled($record->hr_employee_id))
                                                        ->copyable(),
                                                ]),
                                        ]),
                                ]),

                            Section::make('Organization')
                                ->icon(Heroicon::OutlinedBuildingOffice)
                                ->schema([
                                    TextEntry::make('primary_affiliation')
                                        ->label('Primary Affiliation')
                                        ->badge()
                                        ->icon(fn ($state) => $state?->getIcon())
                                        ->color(fn ($state) => $state?->getColor())
                                        ->formatStateUsing(fn ($state) => $state?->getLabel())
                                        ->placeholder('No affiliation'),

                                    TextEntry::make('job_titles')
                                        ->label('Job Titles')
                                        ->badge()
                                        ->placeholder('No job titles')
                                        ->columnSpanFull(),

                                    TextEntry::make('departments')
                                        ->badge()
                                        ->placeholder('No departments')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Section::make('Account')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 1,
                        ])
                        ->schema([
                            Group::make([
                                TextEntry::make('auth_type')
                                    ->label('Authentication Type')
                                    ->badge(),

                                TextEntry::make('netid_inactive')
                                    ->label('NetID Status')
                                    ->formatStateUsing(fn (User $record) => NetIdStatus::getLabel($record))
                                    ->icon(fn (User $record) => NetIdStatus::getIcon($record))
                                    ->iconColor(fn (User $record) => NetIdStatus::getColor($record))
                                    ->default('Unknown'),
                            ])->columns(),

                            TextEntry::make('created_at')
                                ->label('Created')
                                ->inlineLabel()
                                ->dateTime(),

                            TextEntry::make('latest_login_record.logged_in_at')
                                ->label('Last Sign-In')
                                ->placeholder('Never')
                                ->dateTime()
                                ->inlineLabel()
                                ->since()
                                ->dateTimeTooltip(),

                            TextEntry::make('last_directory_sync_at')
                                ->label('Last Directory Sync')
                                ->placeholder('Never')
                                ->dateTime()
                                ->since()
                                ->dateTimeTooltip()
                                ->belowContent([
                                    Action::make('sync')
                                        ->authorize(SystemPermission::EditUsers)
                                        ->label('Refresh from Directory')
                                        ->icon(Heroicon::OutlinedArrowPath)
                                        ->tooltip('Details refresh from the Northwestern Directory each time this person signs in. Refresh them now if they look out of date.')
                                        ->color('warning')
                                        ->size(Size::ExtraSmall)
                                        ->requiresConfirmation()
                                        ->modalHeading('Refresh from the Northwestern Directory?')
                                        ->modalDescription(
                                            'This will pull the latest attributes from the Northwestern Directory and update the user in the platform.'
                                        )
                                        ->modalSubmitActionLabel('Refresh')
                                        ->visible(fn (): bool => filled(config('nusoa.directorySearch.apiKey')))
                                        ->action(function ($record, FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory) {
                                            $user = ($findOrUpdateUserFromDirectory)($record->username, immediate: true);

                                            if ($record->directory_sync_last_failed_at?->getTimestamp() !== $user?->directory_sync_last_failed_at?->getTimestamp()) {
                                                Notification::make()
                                                    ->title('Directory Refresh Failed')
                                                    ->body('The Northwestern Directory may be unavailable, or this person\'s record is incomplete. Try again later.')
                                                    ->danger()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('Directory Refresh Complete')
                                                    ->body("{$user?->username}'s details are up to date.")
                                                    ->success()
                                                    ->send();
                                            }

                                            return redirect()->route('filament.administration.resources.users.view', ['record' => $user]);
                                        }),
                                ]),

                            TextEntry::make('directory_sync_last_failed_at')
                                ->label('Last Directory Sync Failed')
                                ->hidden(fn (User $record) => blank($record->directory_sync_last_failed_at))
                                ->placeholder('Never')
                                ->dateTime()
                                ->since()
                                ->dateTimeTooltip()
                                ->color('danger'),
                        ]),
                ]),
        ]);
    }
}
