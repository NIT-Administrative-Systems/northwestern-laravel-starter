<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Actions;

use App\Domains\Auth\Actions\Api\IssueAccessToken;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use App\Filament\Resources\Users\RelationManagers\AccessTokensRelationManager;
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;

class CreateAccessTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createAccessToken';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::ManageApiUsers)
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
                        if (Session::has(AccessTokenSchemas::SESSION_KEY_CREATE)) {
                            return;
                        }

                        /** @var User $owner */
                        $owner = $livewire->getOwnerRecord();
                        $configuration = AccessTokenSchemas::normalizeConfigurationState($state);

                        [$rawToken, $accessToken] = $issueAccessToken(
                            user: $owner,
                            name: $configuration['name'],
                            expiresAt: $configuration['expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        Session::put([
                            AccessTokenSchemas::SESSION_KEY_CREATE => [
                                'token' => Crypt::encryptString($rawToken),
                                'record_id' => $accessToken->getKey(),
                            ],
                        ]);
                    }),
                Wizard\Step::make('Copy Token')
                    ->schema(
                        AccessTokenSchemas::copyTokenStepSchema(AccessTokenSchemas::SESSION_KEY_CREATE),
                    ),
            ])
            ->modalSubmitAction(fn (Action $action) => AccessTokenSchemas::copyTokenSubmitButton($action))
            ->action(fn () => AccessTokenSchemas::clearTokenSession(AccessTokenSchemas::SESSION_KEY_CREATE))

            ->successNotificationTitle('Access Token created');
    }
}
