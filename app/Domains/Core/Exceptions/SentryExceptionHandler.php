<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use App\Domains\User\Models\User;
use Sentry\State\Scope;
use Throwable;

use function Sentry\configureScope;

class SentryExceptionHandler
{
    public function report(Throwable $exception): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        $this->addSentryContext();
        app('sentry')->captureException($exception);
    }

    private function addSentryContext(): void
    {
        if (! auth()->check()) {
            return;
        }

        configureScope(function (Scope $scope) {
            /** @var User $user */
            $user = auth()->user();

            $scope->setUser([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'primary_affiliation' => $user->primary_affiliation,
                'auth_type' => $user->auth_type,
            ]);
        });
    }
}
