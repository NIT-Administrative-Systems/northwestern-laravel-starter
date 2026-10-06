<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Providers\OAuthServiceProvider;
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
    protected static string $relationship = 'oauth_connections';

    protected static ?string $title = 'Connected Applications';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type !== AuthType::API
            && (config('api.enabled') || config('mcp.enabled'))
            && auth()->user()?->can(SystemPermission::ManageApiAccess);
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
                    ->description(fn (OAuthConnection $record): ?string => $record->oauth_client?->origin === ClientOrigin::Dynamic ? 'AI client · name not verified' : null),
                TextColumn::make('scopes')->label('Allowed To')->badge()->formatStateUsing(fn (string $state): string => OAuthServiceProvider::scopeLabel($state))->placeholder('See Their Account Details'),
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
                    ->authorize(SystemPermission::ManageApiAccess)
                    ->action(function (OAuthConnection $record, DisconnectApplication $disconnect): void {
                        /** @var User $administrator */
                        $administrator = auth()->user();

                        $disconnect($record, $administrator);
                    })
                    ->successNotificationTitle('Application Disconnected'),
            ])
            ->emptyStateHeading('No Connected Applications')
            ->paginated(false);
    }
}
