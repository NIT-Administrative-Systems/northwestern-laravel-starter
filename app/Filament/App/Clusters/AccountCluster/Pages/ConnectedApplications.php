<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster;
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
 * each can do and a way to disconnect it. An administrator impersonating the person sees the
 * list but can't disconnect anything.
 */
class ConnectedApplications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $title = 'Connected applications';

    protected static ?string $slug = 'connected-applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->auth_type !== AuthType::API && ((bool) config('api.enabled') || (bool) config('mcp.enabled'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
            Callout::make()
                ->description(new HtmlString('<strong>You are impersonating this user.</strong> You can see their connected applications, but you can\'t disconnect them. Disconnect an application from the user\'s page in Administration.'))
                ->warning()
                ->visible($this->isImpersonating()),
            Section::make('Applications with access to your account')
                ->description('These applications can act as you, within what each was allowed and your own permissions. Disconnect any you no longer use or don\'t recognize.')
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OAuthConnection::query()
                ->live()
                ->with('oauth_client')
                ->where('user_id', $this->user()->getKey())
                ->latest('connected_at'))
            ->columns([
                TextColumn::make('oauth_client.name')
                    ->label('Application')
                    ->description(fn (OAuthConnection $record): ?string => $record->oauth_client?->origin === ClientOrigin::Dynamic ? 'AI client · self-reported name' : null),
                TextColumn::make('scopes')->label('Allowed to')->badge()->placeholder('See your account details'),
                TextColumn::make('connected_at')->label('Connected')->date(),
                TextColumn::make('last_used_at')->label('Last used')->since()->dateTimeTooltip()->placeholder('Never'),
            ])
            ->headerActions([
                Action::make('disconnectAll')
                    ->label('Disconnect all')
                    ->color('danger')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Disconnect all applications')
                    ->modalDescription('Every application loses access to your account immediately. To use one again, you\'ll need to connect it again.')
                    ->modalSubmitActionLabel('Disconnect all')
                    ->visible(fn (): bool => ! $this->isImpersonating() && OAuthConnection::query()->live()->where('user_id', $this->user()->getKey())->exists())
                    ->action(function (DisconnectApplication $disconnect): void {
                        OAuthConnection::query()->where('user_id', $this->user()->getKey())->each(fn (OAuthConnection $connection) => $disconnect($connection, $this->user()));

                        Notification::make()->title('All applications disconnected')->success()->send();
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
                    ->visible(fn (): bool => ! $this->isImpersonating())
                    ->action(function (OAuthConnection $record, DisconnectApplication $disconnect): void {
                        abort_unless($record->user_id === $this->user()->getKey(), 404);

                        $disconnect($record, $this->user());

                        Notification::make()->title('Application disconnected')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No connected applications')
            ->emptyStateDescription('When you allow an application to act for you, it appears here.')
            ->paginated(false);
    }

    private function isImpersonating(): bool
    {
        return $this->user()->isImpersonated();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
