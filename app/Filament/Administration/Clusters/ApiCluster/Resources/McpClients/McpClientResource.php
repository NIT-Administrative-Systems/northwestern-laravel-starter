<?php

declare(strict_types=1);

namespace App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthConnection;
use App\Filament\Administration\Clusters\ApiCluster;
use App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients\Pages\ListMcpClients;
use App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients\Tables\McpClientsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The clients MCP clients registered for themselves. Their names are self-reported, so an
 * administrator can see who connected each one and revoke any that shouldn't be there.
 * `mcp:prune-clients` removes the ones nobody connects or uses.
 */
class McpClientResource extends Resource
{
    use AuthorizesCredentials;

    protected static ?string $model = OAuthClient::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'MCP Clients';

    protected static ?string $modelLabel = 'MCP client';

    protected static ?string $pluralModelLabel = 'MCP clients';

    protected static ?string $slug = 'mcp-clients';

    protected static ?int $navigationSort = 4;

    /**
     * The model has no policy: {@see self::canAccess()} decides here too, so Filament's strict
     * authorization never looks for one.
     */
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::allowsCredential(CredentialOperation::See, CredentialKind::McpClient);
    }

    /** @return Builder<OAuthClient> */
    public static function getEloquentQuery(): Builder
    {
        return OAuthClient::query()
            ->mcpClients()
            ->withCount(['connections' => fn (Builder $query) => $query->whereIn('oauth_connections.id', OAuthConnection::query()->live()->select('id'))]);
    }

    public static function table(Table $table): Table
    {
        return McpClientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpClients::route('/'),
        ];
    }
}
