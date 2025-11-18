<?php

declare(strict_types=1);

namespace App\Domains\Core\GlobalAlerts;

use App\Domains\User\Models\User;
use Northwestern\SysDev\UI\Services\GlobalAlerts\GlobalAlert;
use Northwestern\SysDev\UI\Services\GlobalAlerts\GlobalAlertDetails;

class UserImpersonated extends GlobalAlert
{
    public function isActive(): bool
    {
        return is_impersonating();
    }

    public function getDetails(): GlobalAlertDetails
    {
        /** @var User $user */
        $user = auth()->user();
        $username = $user->full_name ?? $user->username;

        $leaveUrl = route('impersonate.leave');
        $message = <<<HTML
            <div class="row g-2">
                <div class="col-12">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    Impersonating user • <span class="fw-bold">{$username}</span>
                </div>
                <div class="col-12">
                    <a class="btn btn-outline-danger" href="{$leaveUrl}">
                        Leave Impersonation
                    </a>
                </div>
            </div>
        HTML;

        return new GlobalAlertDetails(
            message: $message,
            style: 'danger',
        );
    }
}
