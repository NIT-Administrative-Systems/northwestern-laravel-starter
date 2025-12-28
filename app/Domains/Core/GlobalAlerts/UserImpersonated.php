<?php

declare(strict_types=1);

namespace App\Domains\Core\GlobalAlerts;

use App\Domains\User\Models\User;
use Illuminate\Support\HtmlString;
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
        $leaveForm = <<<HTML
            <form method="POST" action="{$leaveUrl}">
                {$this->csrfField()}
                <button class="btn btn-outline-danger" type="submit">
                    Leave Impersonation
                </button>
            </form>
        HTML;
        $message = <<<HTML
            <div class="row g-2">
                <div class="col-12">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    Impersonating user • <span class="fw-bold">{$username}</span>
                </div>
                <div class="col-12">
                    {$leaveForm}
                </div>
            </div>
        HTML;

        return new GlobalAlertDetails(
            message: $message,
            style: 'danger',
        );
    }

    private function csrfField(): HtmlString
    {
        return csrf_field();
    }
}
