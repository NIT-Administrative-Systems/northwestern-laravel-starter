<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Actions;

use App\Domains\User\AccountStatusAccess;
use App\Domains\User\Actions\Lifecycle\ReactivateUser;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;

class ReactivateUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reactivateUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (User $record): Response => resolve(AccountStatusAccess::class)->decide(self::actor(), $record))
            ->visible(fn (User $record): bool => $record->trashed())
            ->label('Reactivate')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('Reactivate Account')
            ->modalDescription('They can sign in again. Credentials revoked when the account was deactivated stay revoked.')
            ->modalSubmitActionLabel('Reactivate')
            ->action(fn (User $record, ReactivateUser $reactivate) => $reactivate($record, self::actor()))
            ->successNotificationTitle('Account Reactivated');
    }

    private static function actor(): User
    {
        /** @var User */
        return Filament::auth()->user();
    }
}
