<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class SendLoginCodeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendLoginCode';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::ManageAll)
            ->label('Send Verification Code')
            ->color('info')
            ->outlined()
            ->visible(fn (User $record) => $record->is_local_user && config('local-auth.enabled'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('Sends a new verification code to their email address.')
            ->action(function (User $record) {
                try {
                    resolve(IssueLoginChallenge::class)($record->email, request()->ip(), request()->userAgent());

                    Notification::make()
                        ->success()
                        ->title('Verification Code Sent')
                        ->body('A new code was sent to ' . $record->email . '.')
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title('Verification Code Not Sent')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
