<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\AccessTokens\Actions\CreateAccessTokenAction;
use App\Filament\Resources\AccessTokens\Actions\EditAccessTokenIpRestrictionsAction;
use App\Filament\Resources\AccessTokens\Actions\RevokeAccessTokenAction;
use App\Filament\Resources\AccessTokens\Actions\RotateAccessTokenAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * @property User $ownerRecord
 */
class AccessTokensRelationManager extends RelationManager
{
    protected static string $relationship = 'access_tokens';

    protected static ?string $title = 'Access Tokens';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type === AuthTypeEnum::API;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var User $ownerRecord */
        return Tab::make('Access Tokens')
            ->icon(Heroicon::OutlinedKey);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAccessTokenAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rotated_from_token.id')
                    ->label('Rotated From')
                    ->fontFamily(FontFamily::Mono)
                    ->tooltip('The ID of the token this token was rotated from')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rotated_by_user.clerical_name')
                    ->label('Rotated By')
                    ->tooltip('The user who performed the rotation')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('token_prefix')
                    ->label('Prefix')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn ($state) => $state . '...')
                    ->tooltip('First 5 characters of the token'),
                TextColumn::make('status')
                    ->size(TextSize::Medium)
                    ->badge(),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->placeholder('Never')
                    ->dateTime(),
                TextColumn::make('usage_count')
                    ->label('Uses')
                    ->tooltip('Total successful requests made with the token')
                    ->numeric(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->placeholder(new HtmlString('<span class="text-warning-600 dark:text-warning-400">Never</span>'))
                    ->dateTime('F j, Y'),
                TextColumn::make('allowed_ips')
                    ->label('IP Restrictions')
                    ->badge()
                    ->separator(',')
                    ->placeholder('None')
                    ->tooltip(fn ($record) => $record->allowed_ips ? 'Token is restricted to: ' . implode(', ', $record->allowed_ips) : 'Token accepts requests from any IP address'),
                TextColumn::make('revoked_at')
                    ->label('Revoked At')
                    ->placeholder('N/A')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderByRelevance())
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    RotateAccessTokenAction::make(),
                    EditAccessTokenIpRestrictionsAction::make(),
                    RevokeAccessTokenAction::make(),
                ])->label('Actions')->button(),
            ])
            ->paginated(false);
    }
}
