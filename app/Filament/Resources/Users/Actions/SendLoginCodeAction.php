<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class SendLoginCodeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendLoginCode';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_ALL)
            ->label('Send Verification Code')
            ->color('info')
            ->outlined()
            ->visible(fn (User $record) => $record->is_local_user && config('auth.local.enabled'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('This will send a new verification code to the user\'s email address.')
            ->action(function (User $record) {
                try {
                    resolve(IssueLoginChallenge::class)($record->email, request()->ip(), request()->userAgent());

                    Notification::make()
                        ->success()
                        ->title('Verification code sent')
                        ->body('A new verification code has been sent to ' . $record->email . '.')
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Failed to send verification code')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
