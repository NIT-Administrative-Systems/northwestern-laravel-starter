<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Actions;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
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
            ->visible(fn (User $record) => $record->is_local_user && ! $record->trashed() && resolve(SignIn::class)->offers(SignInMethod::EmailCode))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('Sends a new verification code to their email address.')
            ->action(function (User $record, LoginCodes $loginCodes): void {
                try {
                    $loginCodes->issueFor($record, request());
                } catch (ValidationException $exception) {
                    // The person's hourly limit, which says when to try again.
                    $this->notifyNotSent($exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->notifyNotSent('We couldn\'t send the code. Try again in a few minutes.');

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Verification Code Sent')
                    ->body('A new code was sent to ' . $record->email . '.')
                    ->send();
            });
    }

    private function notifyNotSent(string $reason): void
    {
        Notification::make()
            ->danger()
            ->title('Verification Code Not Sent')
            ->body($reason)
            ->send();
    }
}
