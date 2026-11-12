<?php

declare(strict_types=1);

namespace App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients\Tables;

use App\Domains\Api\Actions\Applications\RevokeOAuthApplication;
use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Models\OAuthClient;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class McpClientsTable
{
    use AuthorizesCredentials;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (OAuthClient $record): string => $record->verifiedBy() !== null ? "Verified by {$record->verifiedBy()}" : 'Self-registered')
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
                TextColumn::make('created_at')->label('Registered')->since()->dateTimeTooltip()->sortable()->visibleFrom('xl'),
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
                    ->action(fn (OAuthClient $record, RevokeOAuthApplication $revoke) => $revoke($record, static::actingUser()))
                    ->successNotificationTitle('MCP Client Revoked')
                    ->visible(fn (OAuthClient $record): bool => $record->status === CredentialStatus::Active
                        && static::allowsCredential(CredentialOperation::Revoke, CredentialKind::McpClient)),
            ])
            ->emptyStateHeading('No MCP Clients')
            ->emptyStateDescription('AI clients register themselves here when someone connects one.');
    }
}
