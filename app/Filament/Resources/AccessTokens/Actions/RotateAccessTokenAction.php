<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Actions;

use App\Domains\User\Actions\Api\RotateAccessToken;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\AccessToken;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class RotateAccessTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rotateAccessToken';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_API_USERS)
            ->label('Rotate')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->steps([
                Wizard\Step::make('Rotate Token')
                    ->schema([
                        Section::make('Notice')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->schema([
                                TextEntry::make('rotate_notice')
                                    ->hiddenLabel()
                                    ->color('warning')
                                    ->default(new HtmlString(<<<'HTML'
Rotating this token will immediately deactivate the current credential and generate a new <code>Bearer</code> token. Any system or integration that relies on this token will need to be updated to use the new value.<br><br>
If you need a transition period, you can create a new token first, update your integration(s) accordingly, and then revoke the current token once the change is complete.
HTML))
                                    ->columnSpanFull(),
                            ]),
                        AccessTokenSchemas::tokenConfigurationSection(),
                    ])
                    ->afterValidation(function (
                        array $state,
                        callable $set,
                        RotateAccessToken $rotateAccessToken,
                        AccessToken $record,
                    ) {
                        // If we've already rotated this token in this wizard session, don't do it again.
                        if (session()->has(AccessTokenSchemas::SESSION_KEY)) {
                            return;
                        }

                        $configuration = AccessTokenSchemas::normalizeConfigurationState($state);

                        $newToken = $rotateAccessToken(
                            previousAccessToken: $record,
                            rotatedBy: auth()->user(),
                            validFrom: $configuration['valid_from'],
                            validTo: $configuration['valid_to'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        session([
                            AccessTokenSchemas::SESSION_KEY => [
                                'token' => $newToken,
                                'record_id' => $record->getKey(),
                            ],
                        ]);
                    }),
                Wizard\Step::make('Copy Token')
                    ->schema(
                        AccessTokenSchemas::copyTokenStepSchema(),
                    ),
            ])
            ->modalSubmitAction(fn (Action $action) => AccessTokenSchemas::copyTokenSubmitButton($action))
            ->action(fn () => AccessTokenSchemas::clearTokenSession())
            ->successNotificationTitle('Access Token rotated')
            ->visible(fn (AccessToken $record): bool => AccessTokenSchemas::canShowRotate($record));
    }
}
