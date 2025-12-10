<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Schemas;

use App\Domains\User\Enums\AccessTokenStatusEnum;
use App\Domains\User\Enums\TokenExpirationEnum;
use App\Domains\User\Models\AccessToken;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Phiki\Grammar\Grammar;

/**
 * Reusable schema fragments and helpers for Access Token related Filament {@see Wizard}s.
 */
class AccessTokenSchemas
{
    /**
     * Session key used by all flows that temporarily expose a raw Access Token
     *
     * The value stored under this key is a small associative array containing:
     * - **token:** `string` - The raw Bearer token
     * - **record_id:** `int|null` - The associated AccessToken ID, when applicable
     * - **user_id:** `int|null` The associated User ID, when applicable
     */
    public const string SESSION_KEY = 'access_token_credentials';

    /**
     * Generic configuration section for token validity and IP restrictions.
     *
     * Usage:
     * - In create flows, no record is bound, so the defaults assume a fresh token.
     * - In rotation flows, an {@see AccessToken} record is bound and values are
     *   pre-filled from that record where appropriate.
     */
    public static function tokenConfigurationSection(): Section
    {
        return Section::make()
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->default(function ($record) {
                        if ($record instanceof AccessToken) {
                            return $record->name;
                        }

                        return null;
                    })
                    ->placeholder('e.g., Apigee Access Token')
                    ->required()
                    ->maxLength(255),

                Select::make('expiration')
                    ->label('Expiration')
                    ->options(TokenExpirationEnum::class)
                    ->placeholder('Select Date')
                    ->required()
                    ->live()
                    ->helperText(function ($state) {
                        if (blank($state)) {
                            return null;
                        }

                        $expiration = $state instanceof TokenExpirationEnum
                            ? $state
                            : TokenExpirationEnum::tryFrom($state);

                        if (! $expiration) {
                            return null;
                        }

                        if ($expiration === TokenExpirationEnum::NEVER) {
                            return new HtmlString('<span class="text-warning-600 dark:text-warning-400">This token will never expire.</span>');
                        }

                        $expiresAt = $expiration->expiresAt();
                        $formattedDate = $expiresAt?->format('F j, Y');

                        return new HtmlString("This token will expire on <strong class=\"text-black dark:text-white\">{$formattedDate}</strong>.");
                    }),

                TagsInput::make('allowed_ips')
                    ->label('Allowed IP Addresses')
                    ->default(function ($record) {
                        if ($record instanceof AccessToken) {
                            return $record->allowed_ips ?? [];
                        }

                        return null;
                    })
                    ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                    ->helperText('Optional. Restrict this token to specific IP addresses or CIDR ranges. Leave empty to allow all IPs.')
                    ->hintIcon(Heroicon::OutlinedInformationCircle)
                    ->hintIconTooltip(
                        'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                    )
                    ->columnSpanFull()
                    ->reorderable(),
            ]);
    }

    /**
     * Normalize token configuration values from a {@see Wizard} step state.
     *
     * This helper extracts the name, converts the selected {@see TokenExpirationEnum} into a concrete
     * {@see CarbonInterface} expiration date (or null for no expiration), and normalizes
     * IP data to a consistent format.
     *
     * @param  array{
     *     name: string,
     *     expiration: TokenExpirationEnum|int,
     *     allowed_ips?: array<int,string>|null
     * }  $state
     * @return array{
     *     name: string,
     *     expires_at: CarbonInterface|null,
     *     allowed_ips: array<int,string>|null
     * }
     */
    public static function normalizeConfigurationState(array $state): array
    {
        $expiration = $state['expiration'] instanceof TokenExpirationEnum
            ? $state['expiration']
            : TokenExpirationEnum::from((int) $state['expiration']);

        $expiresAt = $expiration->expiresAt();

        /** @var array<int,string>|null $allowedIps */
        $allowedIps = filled($state['allowed_ips'] ?? null)
            ? $state['allowed_ips']
            : null;

        return [
            'name' => $state['name'],
            'expires_at' => $expiresAt,
            'allowed_ips' => $allowedIps,
        ];
    }

    /**
     * Schema for the "Copy Token" wizard step, including both:
     * - the raw token display; and
     * - the usage/help content.
     *
     * The token is resolved from {@see self::SESSION_KEY}. If a `record_id` is
     * present, and the current record is an {@see AccessToken}, the token is only
     * shown when the IDs match.
     *
     * @return array<int,Section>
     */
    public static function copyTokenStepSchema(): array
    {
        $copySection = Section::make()
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('warning')
            ->iconSize(IconSize::Large)
            ->description(new HtmlString('Please copy your token and store it securely.<br><strong class="text-black dark:text-white">For security reasons, it will not be shown again.</strong>'))
            ->schema([
                CodeEntry::make('token')
                    ->label('Bearer Token')
                    ->grammar(Grammar::Txt)
                    ->state(function ($record) {
                        $data = session(self::SESSION_KEY);

                        $token = data_get($data, 'token');
                        if (! is_string($token)) {
                            return null;
                        }

                        $recordId = data_get($data, 'record_id');

                        // If this session token is tied to a specific AccessToken record,
                        // only show it when the bound record matches.
                        if ($recordId !== null && $record instanceof AccessToken) {
                            return $record->getKey() === $recordId
                                ? $token
                                : null;
                        }

                        // Create flow
                        return $token;
                    })
                    ->dehydrated(false)
                    ->copyable(),
            ]);

        $usageSection = Section::make('Usage')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconColor('info')
            ->iconSize(IconSize::Large)
            ->schema([
                ViewEntry::make('usage_info')
                    ->hiddenLabel()
                    ->view('filament.resources.users.entries.api-authentication-overview')
                    ->dehydrated(false),
            ]);

        return [
            $copySection,
            $usageSection,
        ];
    }

    /**
     * Apply consistent styling to the "I have copied the token" submit button.
     */
    public static function copyTokenSubmitButton(Action $action): Action
    {
        return $action
            ->label('I have copied the token')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->iconPosition(IconPosition::After)
            ->color('success')
            ->outlined();
    }

    /**
     * Determine whether an Access Token is still mutable (e.g. may be rotated or revoked).
     *
     * A token is considered mutable while it is in the ACTIVE state.
     */
    public static function isMutable(AccessToken $token): bool
    {
        return $token->status === AccessTokenStatusEnum::ACTIVE;
    }

    /**
     * Determine whether the "Rotate" action should be visible for a given token.
     *
     * Rules:
     * - Show for any mutable token; OR
     * - Show for the token currently associated with the in-progress rotation
     *   session (so the wizard can render and close cleanly even if the token
     *   has just transitioned state).
     */
    public static function canShowRotate(AccessToken $token): bool
    {
        if (self::isMutable($token)) {
            return true;
        }

        $data = session(self::SESSION_KEY);

        return is_array($data)
            && ($data['record_id'] ?? null) === $token->getKey();
    }

    /**
     * Clear any stored raw token credentials from the session.
     *
     * This should be called once the operator has confirmed that the token
     * has been copied or when abandoning a token-related wizard.
     */
    public static function clearTokenSession(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
