<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Actions\Personal\RevokePersonalAccessToken;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthToken;
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
 * A person's personal access tokens, which an administrator can revoke, for example when one
 * leaks. The revocation is recorded in the person's audit history.
 */
class PersonalAccessTokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Access Tokens';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type !== AuthType::API
            && config('api.enabled')
            && auth()->user()?->can(SystemPermission::ManageApiAccess);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Access Tokens')->icon(Heroicon::OutlinedKey);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('id', OAuthToken::query()->personal()->select('id'))
                ->orderBy('revoked')
                ->orderByRaw('expires_at <= ?', [now()])
                ->latest())
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('scopes')->label('Scopes')->badge()->placeholder('None'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Created')->dateTime(),
                TextColumn::make('expires_at')->label('Expires')->dateTime('F j, Y'),
                TextColumn::make('last_used_at')->label('Last Used')->dateTime()->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Access Token')
                    ->modalDescription('Anything using this token stops working immediately. This can\'t be undone, and it is recorded in the user\'s audit history.')
                    ->modalSubmitActionLabel('Revoke Token')
                    ->authorize(SystemPermission::ManageApiAccess)
                    ->visible(fn (OAuthToken $record): bool => $record->status === CredentialStatus::Active)
                    ->action(function (OAuthToken $record, RevokePersonalAccessToken $revoke): void {
                        /** @var User $administrator */
                        $administrator = auth()->user();

                        $revoke($record, $administrator);
                    })
                    ->successNotificationTitle('Token revoked'),
            ])
            ->emptyStateHeading('No personal access tokens')
            ->paginated(false);
    }
}
