<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster;
use App\Providers\OAuthServiceProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * The applications a person has allowed to act for them, one row per application, with what
 * each can do and a way to disconnect it. Applications show while the API is on and MCP clients
 * while MCP is on; an administrator impersonating the person sees the list but can't disconnect
 * anything, as {@see \App\Domains\Api\CredentialAccess} decides.
 */
class ConnectedApplications extends Page implements HasTable
{
    use AuthorizesCredentials, InteractsWithTable;

    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $title = 'Connected Applications';

    protected static ?string $slug = 'connected-applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && self::visibleKinds($user) !== [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
            Callout::make()
                ->description(new HtmlString('<strong>You\'re impersonating this person.</strong> You can see their connected applications, but you can\'t disconnect them. To disconnect one, use their page in Administration.'))
                ->warning()
                ->visible($this->disconnecting()->reason === AccessRefusal::Impersonating),
            Section::make('Applications with Access to Your Account')
                ->description('These applications can act as you, within what you allowed and your own permissions. Disconnect any you no longer use or don\'t recognize.')
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->connections()->latest('connected_at'))
            ->columns([
                TextColumn::make('oauth_client.name')
                    ->label('Application')
                    ->description(fn (OAuthConnection $record): ?string => $record->oauth_client?->isMcpClient() ? 'AI client · name not verified' : null),
                TextColumn::make('scopes')->label('Allowed To')->badge()->formatStateUsing(fn (string $state): string => OAuthServiceProvider::scopeLabel($state))->placeholder('See Your Account Details'),
                TextColumn::make('connected_at')->label('Connected')->date(),
                TextColumn::make('last_used_at')->label('Last Used')->since()->dateTimeTooltip()->placeholder('Never'),
            ])
            ->headerActions([
                Action::make('disconnectAll')
                    ->label('Disconnect All')
                    ->color('danger')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Disconnect All Applications')
                    ->modalDescription('Every application loses access to your account immediately. To use one again, you\'ll need to connect it again.')
                    ->modalSubmitActionLabel('Disconnect All')
                    ->visible(fn (): bool => $this->disconnecting()->allowed && $this->connections()->exists())
                    ->action(function (DisconnectApplication $disconnect): void {
                        $this->connections()->each(fn (OAuthConnection $connection) => $disconnect($connection, $this->user()));

                        Notification::make()->title('All Applications Disconnected')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('disconnect')
                    ->label('Disconnect')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (OAuthConnection $record): string => "Disconnect {$record->oauth_client->name}")
                    ->modalDescription('It loses access to your account immediately. To use it again, you\'ll need to connect it again.')
                    ->modalSubmitActionLabel('Disconnect')
                    ->visible(fn (OAuthConnection $record): bool => static::allowsCredential(CredentialOperation::Revoke, CredentialKind::of($record->oauth_client), $this->user()))
                    ->action(function (OAuthConnection $record, DisconnectApplication $disconnect): void {
                        abort_unless($record->user_id === $this->user()->getKey(), 404);

                        $disconnect($record, $this->user());

                        Notification::make()->title('Application Disconnected')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No Connected Applications')
            ->emptyStateDescription('When you allow an application to act for you, it appears here.')
            ->paginated(false);
    }

    /**
     * The person's live connections, of the kinds they may see: applications while the API is
     * on, MCP clients while MCP is on.
     *
     * @return Builder<OAuthConnection>
     */
    private function connections(): Builder
    {
        $kinds = self::visibleKinds($this->user());

        return OAuthConnection::query()
            ->live()
            ->with('oauth_client')
            ->where('user_id', $this->user()->getKey())
            ->when(count($kinds) === 1, fn (Builder $query) => $query->whereHas('oauth_client', fn (Builder $client) => $client->where(
                'origin',
                $kinds === [CredentialKind::McpClient] ? '=' : '!=',
                ClientOrigin::Dynamic,
            )));
    }

    /**
     * Whether the person may disconnect what they see; refused for everything while impersonating.
     */
    private function disconnecting(): AccessDecision
    {
        return static::credentialAccess(CredentialOperation::Revoke, self::visibleKinds($this->user())[0] ?? CredentialKind::ConnectedApplication, $this->user());
    }

    /**
     * @return list<CredentialKind>
     */
    private static function visibleKinds(User $user): array
    {
        return array_values(array_filter(
            [CredentialKind::ConnectedApplication, CredentialKind::McpClient],
            fn (CredentialKind $kind): bool => static::allowsCredential(CredentialOperation::See, $kind, $user),
        ));
    }

    private function user(): User
    {
        return static::actingUser();
    }
}
