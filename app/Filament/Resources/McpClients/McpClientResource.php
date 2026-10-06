<?php

declare(strict_types=1);

namespace App\Filament\Resources\McpClients;

use App\Domains\Auth\Actions\Applications\RevokeOAuthApplication;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\Clusters\ApiCluster;
use App\Filament\Resources\McpClients\Pages\ListMcpClients;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The clients MCP clients registered for themselves. Their names are self-reported, so an
 * administrator can see who connected each one and revoke any that shouldn't be there.
 * `mcp:prune-clients` removes the ones nobody connects or uses.
 */
class McpClientResource extends Resource
{
    protected static ?string $model = OAuthClient::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'MCP Clients';

    protected static ?string $modelLabel = 'MCP client';

    protected static ?string $pluralModelLabel = 'MCP clients';

    protected static ?string $slug = 'mcp-clients';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return (bool) config('mcp.enabled') && (bool) auth()->user()?->can(SystemPermission::ManageApiAccess);
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
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->description('Name not verified')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('redirect_uris')
                    ->label('Redirects To')
                    ->formatStateUsing(fn (string $state): string => parse_url($state, PHP_URL_HOST) ?: (parse_url($state, PHP_URL_SCHEME) ?: $state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('connections_count')->label('Connections')->numeric(),
                TextColumn::make('last_used_at')->label('Last Used')->since()->dateTimeTooltip()->placeholder('Never')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Registered')->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke MCP Client')
                    ->modalDescription('The client loses access to everyone\'s account immediately, and every connection to it is removed. This can\'t be undone.')
                    ->modalSubmitActionLabel('Revoke MCP Client')
                    ->action(fn (OAuthClient $record, RevokeOAuthApplication $revoke) => $revoke($record, self::administrator()))
                    ->successNotificationTitle('MCP Client Revoked')
                    ->visible(fn (OAuthClient $record): bool => $record->status === CredentialStatus::Active),
            ])
            ->emptyStateHeading('No MCP Clients')
            ->emptyStateDescription('AI clients register themselves here when someone connects one.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpClients::route('/'),
        ];
    }

    private static function administrator(): User
    {
        /** @var User */
        return auth()->user();
    }
}
