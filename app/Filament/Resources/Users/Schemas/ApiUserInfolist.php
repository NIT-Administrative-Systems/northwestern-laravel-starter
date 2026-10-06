<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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

class ApiUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 2,
            ])
                ->columnSpanFull()
                ->schema([
                    Section::make('User Overview')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 1,
                        ])
                        ->schema([
                            TextEntry::make('full_name')
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Medium)
                                ->color('primary')
                                ->label('Name'),

                            TextEntry::make('username')
                                ->label('Username')
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),

                            TextEntry::make('email')
                                ->label('Contact Email')
                                ->url(fn ($state) => filled($state) ? 'mailto:' . $state : null)
                                ->openUrlInNewTab()
                                ->placeholder('N/A')
                                ->columnSpanFull(),

                            TextEntry::make('description')
                                ->label('Description')
                                ->placeholder('No description provided')
                                ->columnSpanFull()
                                ->belowContent([
                                    Action::make('edit_description')
                                        ->authorize(SystemPermission::EditUsers)
                                        ->label('Edit Description')
                                        ->icon(Heroicon::OutlinedPencil)
                                        ->color('primary')
                                        ->size(Size::ExtraSmall)
                                        ->modalHeading('Edit API User Description')
                                        ->modalDescription('Optional. What the integration is for, for administrators\' reference.')
                                        ->schema([
                                            Textarea::make('description')
                                                ->label('Description')
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
                                                ->title('Description Updated')
                                                ->body('The description was saved.')
                                                ->success()
                                                ->send();
                                        }),
                                ]),
                        ]),

                    Section::make('Account and Usage')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 1,
                        ])
                        ->schema([
                            TextEntry::make('auth_type')
                                ->label('Authentication Type')
                                ->inlineLabel()
                                ->badge(),

                            Group::make([
                                TextEntry::make('active_clients_count')
                                    ->label('Active Service Clients')
                                    ->inlineLabel()
                                    ->getStateUsing(fn (User $record) => number_format(OAuthClient::query()->whereMorphedTo('owner', $record)->active()->count())),

                                TextEntry::make('last_api_request_at')
                                    ->label('Last API Request')
                                    ->tooltip('The most recent request from any of its service clients')
                                    ->placeholder('Never')
                                    ->inlineLabel()
                                    ->dateTime()
                                    ->since()
                                    ->dateTimeTooltip()
                                    ->getStateUsing(fn (User $record) => OAuthClient::query()->whereMorphedTo('owner', $record)->max('last_used_at')),
                            ])
                                ->columns(1),

                            TextEntry::make('created_at')
                                ->label('Created')
                                ->inlineLabel()
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }
}
