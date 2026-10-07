<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Resources\ServiceClients\Actions\CreateServiceClientAction;
use App\Filament\Resources\ServiceClients\Actions\EditServiceClientIpRestrictionsAction;
use App\Filament\Resources\ServiceClients\Actions\RevokeServiceClientAction;
use App\Filament\Resources\ServiceClients\Actions\RotateServiceClientAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An API user's service clients: the client credentials an integration uses to get access
 * tokens that act as the API user.
 */
class ServiceClientsRelationManager extends RelationManager
{
    use AuthorizesCredentials;

    protected static string $relationship = 'oauthApps';

    protected static ?string $title = 'Service Clients';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return static::allowsCredential(CredentialOperation::See, CredentialKind::ServiceClient, $ownerRecord);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Service Clients')
            ->icon(Heroicon::OutlinedKey);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateServiceClientAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('id')
                    ->label('Client ID')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->limit(13)
                    ->tooltip(fn (OAuthClient $record): string => $record->id),
                TextColumn::make('status')
                    ->size(TextSize::Medium)
                    ->badge(),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->placeholder('Never')
                    ->dateTime(),
                TextColumn::make('secret_expires_at')
                    ->label('Secret Expires')
                    ->dateTime('F j, Y'),
                TextColumn::make('allowed_ips')
                    ->label('IP Restrictions')
                    ->badge()
                    ->separator(',')
                    ->placeholder('None')
                    ->tooltip(fn (OAuthClient $record): string => filled($record->allowed_ips)
                        ? 'The client is restricted to: ' . implode(', ', $record->allowed_ips)
                        : 'The client accepts requests from any IP address'),
                TextColumn::make('rotated_from_client.name')
                    ->label('Replaces')
                    ->tooltip('The service client this one replaced')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rotated_by_user.clerical_name')
                    ->label('Rotated By')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Usable clients first, then the most recently used.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['rotated_from_client', 'rotated_by_user'])
                ->orderBy('revoked')
                ->orderByRaw('last_used_at IS NULL')
                ->latest('last_used_at')
                ->latest())
            ->recordActions([
                ActionGroup::make([
                    RotateServiceClientAction::make(),
                    EditServiceClientIpRestrictionsAction::make(),
                    RevokeServiceClientAction::make(),
                ])->label('Actions')->button(),
            ])
            ->paginated(false);
    }

    /**
     * The API user whose service clients these are, the holder its actions ask about.
     */
    public function apiUser(): User
    {
        /** @var User */
        return $this->getOwnerRecord();
    }
}
