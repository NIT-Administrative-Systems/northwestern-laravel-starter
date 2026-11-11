<?php

declare(strict_types=1);

namespace App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthConnection;
use App\Filament\Administration\Clusters\ApiCluster;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\Pages\ListOAuthApplications;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\Tables\OAuthApplicationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * External applications people connect to their account through
 * the authorization code flow. Administrators register them.
 * Clients that registered themselves aren't listed here.
 */
class OAuthApplicationResource extends Resource
{
    use AuthorizesCredentials;

    protected static ?string $model = OAuthClient::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'application';

    protected static ?string $pluralModelLabel = 'applications';

    protected static ?string $slug = 'applications';

    protected static ?int $navigationSort = 3;

    /**
     * The model has no policy, so canAccess() decides here
     * too and strict authorization never looks for one.
     */
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::allowsCredential(CredentialOperation::See, CredentialKind::ConnectedApplication);
    }

    /** @return Builder<OAuthClient> */
    public static function getEloquentQuery(): Builder
    {
        return OAuthClient::query()
            ->applications()
            ->withCount(['connections' => fn (Builder $query) => $query->whereIn('oauth_connections.id', OAuthConnection::query()->live()->select('id'))]);
    }

    public static function table(Table $table): Table
    {
        return OAuthApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthApplications::route('/'),
        ];
    }
}
