<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Actions;

use App\Domains\Auth\Actions\Api\IssueAccessToken;
use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use App\Filament\Resources\Users\RelationManagers\AccessTokensRelationManager;
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;

class CreateAccessTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createAccessToken';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_API_USERS)
            ->label('Create Token')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->outlined()
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->steps([
                Wizard\Step::make('Configure Token')
                    ->schema([
                        AccessTokenSchemas::tokenConfigurationSection(),
                    ])
                    ->afterValidation(function (
                        array $state,
                        callable $set,
                        IssueAccessToken $issueAccessToken,
                        AccessTokensRelationManager $livewire,
                    ) {
                        /** @var User $owner */
                        $owner = $livewire->getOwnerRecord();
                        $configuration = AccessTokenSchemas::normalizeConfigurationState($state);

                        [$rawToken, $accessToken] = $issueAccessToken(
                            user: $owner,
                            name: $configuration['name'],
                            expiresAt: $configuration['expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        session([
                            AccessTokenSchemas::SESSION_KEY => [
                                'token' => $rawToken,
                                'record_id' => $accessToken->getKey(),
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

            ->successNotificationTitle('Access Token created');
    }
}
