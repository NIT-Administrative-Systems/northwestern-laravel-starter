<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Support\NetIdStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                                                            'c' => md5((string) $record->wildcard_photo_last_synced_at->toString()),
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
                                                        ->copyable(),

                                                    TextEntry::make('email')
                                                        ->label('Email Address')
                                                        ->url(fn ($state) => filled($state) ? 'mailto:' . $state : null)
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
                                ->label('Last Login')
                                ->placeholder('Never')
                                ->dateTime()
                                ->inlineLabel()
                                ->since()
                                ->dateTimeTooltip(),

                            TextEntry::make('last_directory_sync_at')
                                ->label('Last Directory Sync At')
                                ->placeholder('Never')
                                ->dateTime()
                                ->since()
                                ->dateTimeTooltip()
                                ->belowContent([
                                    Action::make('sync')
                                        ->authorize(PermissionEnum::EDIT_USERS)
                                        ->label('Force Sync')
                                        ->icon(Heroicon::OutlinedArrowPath)
                                        ->tooltip('User data syncs automatically with the Northwestern Directory during login. If this appears out of date, you may force a refresh.')
                                        ->color('warning')
                                        ->size(Size::ExtraSmall)
                                        ->requiresConfirmation()
                                        ->modalHeading('Force Northwestern Directory Refresh?')
                                        ->modalDescription(
                                            'This will pull the latest attributes from the Northwestern Directory and update the user in the platform.'
                                        )
                                        ->modalSubmitActionLabel('Start Sync')
                                        ->action(function ($record, FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory) {
                                            $user = ($findOrUpdateUserFromDirectory)($record->username, immediate: true);

                                            if ($record->directory_sync_last_failed_at !== $user?->directory_sync_last_failed_at) {
                                                Notification::make()
                                                    ->title('Directory sync failed')
                                                    ->body('The Northwestern Directory may be unavailable or the user has an incomplete record. Please try again later.')
                                                    ->danger()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('Directory sync complete')
                                                    ->body("Northwestern Directory data has been synced for {$user?->username}.")
                                                    ->success()
                                                    ->send();
                                            }

                                            return redirect()->to(UserResource::getUrl('view', ['record' => $user]));
                                        }),
                                ]),

                            TextEntry::make('directory_sync_last_failed_at')
                                ->label('Last Directory Sync Failed At')
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
