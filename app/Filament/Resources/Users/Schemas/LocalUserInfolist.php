<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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

class LocalUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 3,
            ])
                ->columnSpanFull()
                ->schema([
                    // Left side: overview + notes (2/3 width)
                    Grid::make(1)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 2,
                        ])
                        ->schema([
                            Section::make('User Overview')
                                ->icon(Heroicon::OutlinedIdentification)
                                ->schema([
                                    // Two-column responsive layout
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                                        ->schema([
                                            // Left column: name + identifiers
                                            Group::make([
                                                TextEntry::make('full_name')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Medium)
                                                    ->color('primary')
                                                    ->label('Name'),

                                                TextEntry::make('email')
                                                    ->label('Email Address')
                                                    ->url(fn ($state) => filled($state) ? 'mailto:' . $state : null)
                                                    ->placeholder('N/A'),

                                                TextEntry::make('username')
                                                    ->label('Username')
                                                    ->copyable()
                                                    ->visible(fn (User $record) => $record->username !== $record->email),
                                            ]),

                                            // Right column: job title + organization
                                            Group::make([
                                                TextEntry::make('job_titles')
                                                    ->label('Job Title')
                                                    ->badge()
                                                    ->placeholder('No job title set'),

                                                TextEntry::make('departments')
                                                    ->label('Organization')
                                                    ->badge()
                                                    ->placeholder('No organization set'),
                                            ]),
                                        ]),

                                    // Full-width editable notes row
                                    TextEntry::make('description')
                                        ->label('Notes')
                                        ->placeholder('No notes')
                                        ->columnSpanFull()
                                        ->belowContent([
                                            Action::make('edit_description')
                                                ->authorize(PermissionEnum::EDIT_USERS)
                                                ->label('Edit Notes')
                                                ->icon(Heroicon::OutlinedPencil)
                                                ->color('primary')
                                                ->size(Size::ExtraSmall)
                                                ->modalHeading('Edit Notes')
                                                ->modalDescription('Optional internal notes about this user.')
                                                ->schema([
                                                    Textarea::make('description')
                                                        ->label('Notes')
                                                        ->rows(5)
                                                        ->maxLength(1000),
                                                ])
                                                ->fillForm(fn (User $record) => [
                                                    'description' => $record->description,
                                                ])
                                                ->action(function (User $record, array $data) {
                                                    $record->update([
                                                        'description' => $data['description'],
                                                    ]);

                                                    Notification::make()
                                                        ->title('Notes updated')
                                                        ->body('The local user notes have been updated.')
                                                        ->success()
                                                        ->send();
                                                }),
                                        ]),
                                ]),
                        ]),

                    // Right side: account (1/3 width)
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
                            ])->columns(),

                            TextEntry::make('created_at')
                                ->label('Created')
                                ->inlineLabel()
                                ->dateTime(),

                            TextEntry::make('latest_login_record.logged_in_at')
                                ->label('Last Login')
                                ->inlineLabel()
                                ->placeholder('Never')
                                ->dateTime()
                                ->since()
                                ->dateTimeTooltip(),
                        ]),
                ]),
        ]);
    }
}
