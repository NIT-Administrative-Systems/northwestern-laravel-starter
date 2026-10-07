<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\ServiceClients\Schemas;

use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Support\RevealOnceSecret;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Rules\ValidIpOrCidrRule;

/**
 * Reusable schema fragments for the service client {@see Wizard}s. Each wizard shows the new
 * client's secret once, through {@see RevealOnceSecret}.
 */
class ServiceClientSchemas
{
    /**
     * The client's name, secret lifetime and IP restrictions. In rotation flows the previous
     * client is bound and its values pre-fill the form.
     */
    public static function clientConfigurationSection(): Section
    {
        return Section::make()
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->default(fn ($record) => $record instanceof OAuthClient ? $record->name : null)
                    ->placeholder('e.g., Apigee Production')
                    ->required()
                    ->maxLength(255),

                Select::make('expiration')
                    ->label('Secret Expires')
                    ->options(TokenExpiration::class)
                    ->required()
                    ->live()
                    ->helperText(function ($state) {
                        $note = 'A secret can\'t be extended. Rotate the client to issue a new one.';

                        $expiration = $state instanceof TokenExpiration ? $state : TokenExpiration::tryFrom((int) $state);

                        if (! $expiration instanceof TokenExpiration) {
                            return $note;
                        }

                        $formattedDate = $expiration->expiresAt()->format('F j, Y');

                        return new HtmlString("The secret will expire on <strong class=\"text-black dark:text-white\">{$formattedDate}</strong>.<br>{$note}");
                    }),

                TagsInput::make('allowed_ips')
                    ->label('Allowed IP Addresses')
                    ->default(fn ($record) => $record instanceof OAuthClient ? ($record->allowed_ips ?? []) : null)
                    ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                    ->helperText('Leave empty to allow any address. Use CIDR notation for ranges, such as 10.0.0.0/8.')
                    ->hintIcon(Heroicon::OutlinedInformationCircle)
                    ->hintIconTooltip(
                        'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                    )
                    ->nestedRecursiveRules([new ValidIpOrCidrRule()])
                    ->columnSpanFull()
                    ->reorderable(),
            ]);
    }

    /**
     * @param  array{name: string, expiration: TokenExpiration|int|string, allowed_ips?: array<int,string>|null}  $state
     * @return array{name: non-empty-string, secret_expires_at: CarbonInterface, allowed_ips: list<non-empty-string>|null}
     */
    public static function normalizeConfigurationState(array $state): array
    {
        $expiration = $state['expiration'] instanceof TokenExpiration
            ? $state['expiration']
            : TokenExpiration::from((int) $state['expiration']);

        /** @var list<non-empty-string>|null $allowedIps */
        $allowedIps = filled($state['allowed_ips'] ?? null) ? array_values($state['allowed_ips']) : null;

        /** @var non-empty-string $name */
        $name = $state['name'];

        return [
            'name' => $name,
            'secret_expires_at' => $expiration->expiresAt(),
            'allowed_ips' => $allowedIps,
        ];
    }

    /**
     * The "Copy Credentials" step: the client ID and secret, and how to use them.
     *
     * @return array<int, Section>
     */
    public static function copyCredentialsStepSchema(RevealOnceSecret $secret): array
    {
        return [
            Section::make()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->iconColor('warning')
                ->iconSize(IconSize::Large)
                ->description(new HtmlString('Copy the client ID and secret and store them somewhere safe.<br><strong class="text-black dark:text-white">The secret won\'t be shown again.</strong>'))
                ->schema([
                    $secret->identifierEntry('client_id', 'Client ID'),
                    $secret->secretEntry('client_secret', 'Client Secret'),
                ]),
            Section::make('Usage')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->iconColor('info')
                ->iconSize(IconSize::Large)
                ->schema([
                    ViewEntry::make('usage_info')
                        ->hiddenLabel()
                        ->view('filament.resources.users.entries.api-authentication-overview')
                        ->dehydrated(false),
                ]),
        ];
    }

    public static function copyCredentialsSubmitButton(Action $action): Action
    {
        return $action
            ->label('I\'ve copied the secret')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->iconPosition(IconPosition::After)
            ->color('success')
            ->outlined();
    }

    /**
     * Whether a client can still be changed: rotated, restricted or revoked.
     */
    public static function isMutable(OAuthClient $client): bool
    {
        return $client->status === CredentialStatus::Active;
    }
}
