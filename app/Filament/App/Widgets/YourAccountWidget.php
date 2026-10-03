<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\ContactSupport;
use App\Providers\Filament\AppPanelProvider;
use Filament\Widgets\Widget;

/**
 * The signed-in user's account on the app panel's dashboard: a greeting, their previous
 * sign-in, the roles they hold beyond the default Northwestern User role, and where to get
 * help. Replace or delete it as the application grows its own dashboard.
 */
class YourAccountWidget extends Widget
{
    protected static ?int $sort = -10;

    // Render with the page: it is one query, and a greeting that pops in after load looks broken.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.your-account';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        // The newest record is the current sign-in, so the previous one is second.
        $previousSignIn = $user->login_records()
            ->latest('logged_in_at')
            ->skip(1)
            ->first();

        return [
            'firstName' => filled($user->first_name) ? $user->first_name : $user->full_name,
            'previousSignInAt' => $previousSignIn?->logged_in_at,
            'signInMethod' => match ($user->auth_type) {
                AuthType::SSO => 'your NetID',
                AuthType::Local => 'an email verification code',
                default => $user->auth_type->getLabel(),
            },
            'roles' => $user->non_default_roles->pluck('name')->sort()->values()->all(),
            'contactSupportUrl' => ContactSupport::canAccess() ? ContactSupport::getUrl(panel: AppPanelProvider::ID) : null,
            'documentationUrl' => config('support.documentation_url') ?: null,
        ];
    }
}
