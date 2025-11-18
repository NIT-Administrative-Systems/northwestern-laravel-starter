<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiTokens\Actions;

use App\Domains\User\Actions\IssueApiToken;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\ApiTokens\Schemas\ApiTokenSchemas;
use App\Filament\Resources\Users\RelationManagers\ApiTokensRelationManager;
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;

class CreateApiTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createApiToken';
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
                        ApiTokenSchemas::tokenConfigurationSection(),
                    ])
                    ->afterValidation(function (
                        array $state,
                        callable $set,
                        IssueApiToken $issueApiToken,
                        ApiTokensRelationManager $livewire,
                    ) {
                        /** @var User $owner */
                        $owner = $livewire->getOwnerRecord();
                        $configuration = ApiTokenSchemas::normalizeConfigurationState($state);

                        [$rawToken, $apiToken] = $issueApiToken(
                            user: $owner,
                            validFrom: $configuration['valid_from'],
                            validTo: $configuration['valid_to'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        session([
                            ApiTokenSchemas::SESSION_KEY => [
                                'token' => $rawToken,
                                'record_id' => $apiToken->getKey(),
                            ],
                        ]);
                    }),
                Wizard\Step::make('Copy Token')
                    ->schema(
                        ApiTokenSchemas::copyTokenStepSchema(),
                    ),
            ])
            ->modalSubmitAction(fn (Action $action) => ApiTokenSchemas::copyTokenSubmitButton($action))
            ->action(fn () => ApiTokenSchemas::clearTokenSession())

            ->successNotificationTitle('API token created');
    }
}
