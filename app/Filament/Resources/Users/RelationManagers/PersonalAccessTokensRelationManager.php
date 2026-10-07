<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Personal\RevokePersonalAccessToken;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Models\OAuthToken;
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
 * A person's personal access tokens, which an administrator can revoke, for example when one
 * leaks. The revocation is recorded in the person's audit history.
 */
class PersonalAccessTokensRelationManager extends RelationManager
{
    use AuthorizesCredentials;

    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Personal Access Tokens';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return static::allowsCredential(CredentialOperation::See, CredentialKind::PersonalAccessToken, $ownerRecord);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Personal Access Tokens')->icon(Heroicon::OutlinedKey);
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
                TextColumn::make('scopes')
                    ->label('Access')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OAuthServiceProvider::scopeLabel($state))
                    ->placeholder('None'),
                TextColumn::make('status')->label('Status')->badge(),
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
                    ->modalHeading('Revoke Token')
                    ->modalDescription('Anything using this token stops working immediately. This can\'t be undone, and it\'s recorded in the person\'s audit history.')
                    ->modalSubmitActionLabel('Revoke Token')
                    ->visible(fn (OAuthToken $record): bool => $record->status === CredentialStatus::Active
                        && static::allowsCredential(CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $this->person()))
                    ->action(fn (OAuthToken $record, RevokePersonalAccessToken $revoke) => $revoke($record, static::actingUser()))
                    ->successNotificationTitle('Token Revoked'),
            ])
            ->emptyStateHeading('No Personal Access Tokens')
            ->paginated(false);
    }

    private function person(): User
    {
        /** @var User */
        return $this->getOwnerRecord();
    }
}
