<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\RelationManagers;

use App\Domains\Api\Actions\Applications\DisconnectApplication;
use App\Domains\Api\ApiScopes;
use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The applications a person has connected, which an administrator can disconnect. The
 * disconnect is recorded in the person's audit history.
 */
class ConnectedApplicationsRelationManager extends RelationManager
{
    use AuthorizesCredentials;

    protected static string $relationship = 'oauth_connections';

    protected static ?string $title = 'Connected Applications';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return static::allowsCredential(CredentialOperation::See, CredentialKind::ConnectedApplication, $ownerRecord)
            || static::allowsCredential(CredentialOperation::See, CredentialKind::McpClient, $ownerRecord);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Connected Applications')->icon(Heroicon::OutlinedLink);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('oauth_client.name')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('id', OAuthConnection::query()->live()->select('id'))->with('oauth_client')->latest('connected_at'))
            ->columns([
                TextColumn::make('oauth_client.name')
                    ->label('Application')
                    ->description(fn (OAuthConnection $record): ?string => $record->oauth_client?->isMcpClient() ? 'AI client · name not verified' : null),
                TextColumn::make('scopes')->label('Allowed To')->badge()->formatStateUsing(fn (string $state): string => ApiScopes::label($state))->placeholder('See Their Account Details'),
                TextColumn::make('connected_at')->label('Connected')->dateTime(),
                TextColumn::make('last_used_at')->label('Last Used')->dateTime()->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('disconnect')
                    ->label('Disconnect')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Disconnect Application')
                    ->modalDescription('The application loses access to this person\'s account immediately. This is recorded in their audit history.')
                    ->modalSubmitActionLabel('Disconnect')
                    ->visible(fn (OAuthConnection $record): bool => static::allowsCredential(CredentialOperation::Revoke, CredentialKind::of($record->oauth_client), $this->person()))
                    ->action(fn (OAuthConnection $record, DisconnectApplication $disconnect) => $disconnect($record, static::actingUser()))
                    ->successNotificationTitle('Application Disconnected'),
            ])
            ->emptyStateHeading('No Connected Applications')
            ->paginated(false);
    }

    private function person(): User
    {
        /** @var User */
        return $this->getOwnerRecord();
    }
}
