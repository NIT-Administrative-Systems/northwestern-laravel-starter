<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Actions;

use App\Domains\User\AccountStatusAccess;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;

class DeactivateUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deactivateUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (User $record): Response => resolve(AccountStatusAccess::class)->decide($this->actor(), $record))
            ->visible(fn (User $record): bool => ! $record->trashed())
            ->label('Deactivate')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('Deactivate Account')
            ->modalDescription('They\'re signed out everywhere and can\'t sign in, and their personal access tokens, connected applications and service clients are revoked. Their account and history are kept, and you can reactivate it later.')
            ->modalSubmitActionLabel('Deactivate')
            ->action(fn (User $record, DeactivateUser $deactivate) => $deactivate($record, $this->actor()))
            ->successNotificationTitle('Account Deactivated');
    }

    private function actor(): User
    {
        /** @var User */
        return Filament::auth()->user();
    }
}
