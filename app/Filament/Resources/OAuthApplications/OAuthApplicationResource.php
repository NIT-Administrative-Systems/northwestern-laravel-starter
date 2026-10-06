<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthApplications;

use App\Domains\Auth\Actions\Applications\RegenerateOAuthApplicationSecret;
use App\Domains\Auth\Actions\Applications\RevokeOAuthApplication;
use App\Domains\Auth\Actions\Applications\UpdateOAuthApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Filament\Clusters\ApiCluster;
use App\Filament\Resources\OAuthApplications\Pages\ListOAuthApplications;
use App\Filament\Resources\OAuthApplications\Schemas\OAuthApplicationSchemas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * External applications that people can connect to their account through the authorization
 * code flow. Administrators register them; dynamically registered clients aren't listed.
 */
class OAuthApplicationResource extends Resource
{
    protected static ?string $model = OAuthClient::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'application';

    protected static ?string $pluralModelLabel = 'applications';

    protected static ?string $slug = 'applications';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) config('api.enabled') && (bool) auth()->user()?->can(SystemPermission::ManageApiAccess);
    }

    /** @return Builder<OAuthClient> */
    public static function getEloquentQuery(): Builder
    {
        return OAuthClient::query()
            ->where('origin', ClientOrigin::Administrator)
            ->where('grant_types', 'like', '%"authorization_code"%')
            ->withCount(['connections' => fn (Builder $query) => $query->whereIn('oauth_connections.id', OAuthConnection::query()->live()->select('id'))]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('id')
                    ->label('Client ID')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->limit(13)
                    ->tooltip(fn (OAuthClient $record): string => $record->id),
                TextColumn::make('redirect_uris')
                    ->label('Redirects To')
                    ->formatStateUsing(fn (string $state): string => parse_url($state, PHP_URL_HOST) ?: $state)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('scopes')->label('Allowed Scopes')->badge()->placeholder('None'),
                IconColumn::make('first_party')->label('First-Party')->boolean(),
                TextColumn::make('connections_count')->label('Connections')->numeric(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Registered')->date()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label('Edit')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->schema(OAuthApplicationSchemas::detailsFields())
                        ->fillForm(fn (OAuthClient $record): array => $record->only(['name', 'description', 'contact_email', 'redirect_uris', 'scopes', 'first_party']))
                        ->action(fn (OAuthClient $record, array $data, UpdateOAuthApplication $update) => $update(
                            $record,
                            $data['name'],
                            array_values($data['redirect_uris']),
                            array_values($data['scopes'] ?? []),
                            (bool) $data['first_party'],
                            $data['description'] ?? null,
                            $data['contact_email'] ?? null,
                        ))
                        ->successNotificationTitle('Application Updated')
                        ->visible(fn (OAuthClient $record): bool => $record->status === CredentialStatus::Active),
                    Action::make('regenerateSecret')
                        ->label('Regenerate Secret')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->closeModalByClickingAway(false)
                        ->closeModalByEscaping(false)
                        ->steps([
                            Wizard\Step::make('Regenerate')
                                ->description('The current secret stops working immediately. Update the application with the new one.')
                                ->afterValidation(function (OAuthClient $record, RegenerateOAuthApplicationSecret $regenerate): void {
                                    if (filled(session(OAuthApplicationSchemas::SESSION_KEY))) {
                                        return;
                                    }

                                    OAuthApplicationSchemas::storeCredentials($record, $regenerate($record));
                                }),
                            Wizard\Step::make('Copy Secret')->schema(OAuthApplicationSchemas::credentialsStep()),
                        ])
                        ->modalSubmitActionLabel('I\'ve copied the secret')
                        ->action(fn () => OAuthApplicationSchemas::clearCredentials())
                        ->visible(fn (OAuthClient $record): bool => $record->confidential() && $record->status === CredentialStatus::Active),
                    Action::make('revoke')
                        ->label('Revoke')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke Application')
                        ->modalDescription('The application loses access to everyone\'s account immediately, and every connection to it is removed. This can\'t be undone.')
                        ->modalSubmitActionLabel('Revoke Application')
                        ->action(fn (OAuthClient $record, RevokeOAuthApplication $revoke) => $revoke($record))
                        ->successNotificationTitle('Application Revoked')
                        ->visible(fn (OAuthClient $record): bool => $record->status === CredentialStatus::Active),
                ])->label('Actions')->button(),
            ])
            ->emptyStateHeading('No Applications Registered')
            ->emptyStateDescription('Register an application so people can connect it to their account.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthApplications::route('/'),
        ];
    }
}
