<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Actions\Personal\CreatePersonalAccessToken;
use App\Domains\Auth\Actions\Personal\RevokePersonalAccessToken;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster;
use App\Providers\OAuthServiceProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Phiki\Grammar\Grammar;

/**
 * A person's personal access tokens, for calling the API as themselves from their own code. Shown
 * to holders of {@see SystemPermission::CreatePersonalAccessTokens}.
 *
 * A new token is shown once. Between the wizard's steps it is kept encrypted in the session,
 * never in Livewire state, and forgotten when the person confirms they have copied it. An
 * administrator impersonating the person sees the tokens but can't create or revoke them.
 */
class AccessTokens extends Page implements HasTable
{
    use InteractsWithTable;

    /** Session key for the token being shown once. Stored value: `['token' => string (encrypted), 'record_id' => string]` */
    public const string SESSION_KEY = 'personal_access_token:create';

    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $title = 'Access Tokens';

    protected static ?string $slug = 'access-tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->auth_type !== AuthType::API
            && (bool) config('api.enabled')
            && $user->can(SystemPermission::CreatePersonalAccessTokens);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
            Callout::make()
                ->description(new HtmlString('<strong>You\'re impersonating this person.</strong> You can see their tokens, but you can\'t create or revoke them. To revoke one, use their page in Administration.'))
                ->warning()
                ->visible($this->isImpersonating()),
            Section::make('Personal Access Tokens')
                ->description('Use the API as yourself from your own code and tools. A token can do only what you choose for it, and never more than you can. Keep tokens secret: anyone with one can act as you.')
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OAuthToken::query()
                ->personal()
                ->where('user_id', $this->user()->getKey())
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
                TextColumn::make('created_at')->label('Created')->date(),
                TextColumn::make('expires_at')->label('Expires')->date(),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never'),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Token')
                    ->modalDescription('Anything using this token stops working immediately. This can\'t be undone.')
                    ->modalSubmitActionLabel('Revoke Token')
                    ->visible(fn (OAuthToken $record): bool => ! $this->isImpersonating() && $record->status === CredentialStatus::Active)
                    ->action(function (OAuthToken $record, RevokePersonalAccessToken $revoke): void {
                        abort_unless($record->user_id === $this->user()->getKey(), 404);

                        $revoke($record, $this->user());

                        Notification::make()->title('Token Revoked')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No Personal Access Tokens')
            ->emptyStateDescription('Create a token to use the API as yourself.')
            ->paginated(false);
    }

    private function createAction(): Action
    {
        $maxDays = (int) config('api.personal_access_tokens.max_lifetime_days');

        return Action::make('createToken')
            ->label('Create Token')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->hidden(fn (): bool => $this->isImpersonating())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->mountUsing(function (?Schema $schema): void {
                // A token from an abandoned run of this wizard is never shown again.
                Session::forget(self::SESSION_KEY);
                $schema?->fill();
            })
            ->steps([
                Wizard\Step::make('Configure')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->placeholder('e.g., Nightly enrollment export')
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('scopes')
                            ->label('Access')
                            ->options(fn (): array => CreatePersonalAccessToken::scopesFor($this->user()))
                            ->helperText('What the token can do. Choose only what it needs.'),
                        Select::make('lifetime')
                            ->label('Expires After')
                            ->options(collect(TokenExpiration::forPersonalAccessTokens($maxDays))->mapWithKeys(fn (TokenExpiration $lifetime): array => [$lifetime->value => $lifetime->getLabel()]))
                            ->default(TokenExpiration::ThreeMonths->value <= $maxDays ? TokenExpiration::ThreeMonths->value : null)
                            ->required()
                            ->selectablePlaceholder(false),
                    ])
                    ->afterValidation(function (array $state, CreatePersonalAccessToken $create): void {
                        if (Session::has(self::SESSION_KEY)) {
                            return;
                        }

                        try {
                            [$accessToken, $token] = $create(
                                $this->user(),
                                $state['name'],
                                array_values($state['scopes'] ?? []),
                                TokenExpiration::from((int) $state['lifetime']),
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            throw new Halt($e->getMessage(), $e->getCode(), $e);
                        }

                        Session::put(self::SESSION_KEY, ['token' => Crypt::encryptString($accessToken), 'record_id' => $token->getKey()]);
                    }),
                Wizard\Step::make('Copy Token')
                    ->schema([
                        Text::make(new HtmlString('Copy the token and store it somewhere safe. <strong>It won\'t be shown again.</strong> Send it in the <code>Authorization: Bearer</code> header.')),
                        CodeEntry::make('token')
                            ->label('Token')
                            ->grammar(Grammar::Txt)
                            ->state(function (): ?string {
                                $stored = Session::get(self::SESSION_KEY);

                                return is_array($stored) && is_string($stored['token'] ?? null) ? Crypt::decryptString($stored['token']) : null;
                            })
                            ->dehydrated(false)
                            ->copyable(),
                    ]),
            ])
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Done')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->iconPosition(IconPosition::After))
            ->action(fn () => Session::forget(self::SESSION_KEY))
            ->successNotificationTitle('Token Created');
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
