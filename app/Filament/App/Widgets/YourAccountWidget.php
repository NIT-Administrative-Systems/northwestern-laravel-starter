<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\Profile;
use App\Filament\App\Pages\ContactSupport;
use App\Providers\Filament\AppPanelProvider;
use Filament\Widgets\Widget;

/**
 * The signed-in user's account on the app panel's dashboard: a greeting (welcome back, after
 * the first sign-in), the roles they hold beyond the default Northwestern User role, a link to
 * their Account area, and where to get help. Replace or delete it as the application grows its own dashboard.
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

        return [
            'firstName' => filled($user->first_name) ? $user->first_name : $user->full_name,
            // The current sign-in is recorded too, so a returning user has more than one.
            'returning' => $user->login_records()->skip(1)->exists(),
            'accountUrl' => Profile::getUrl(panel: AppPanelProvider::ID),
            'roles' => $user->non_default_roles->pluck('name')->sort()->values()->all(),
            'contactSupportUrl' => ContactSupport::canAccess() ? ContactSupport::getUrl(panel: AppPanelProvider::ID) : null,
            'documentationUrl' => config('support.documentation_url') ?: null,
        ];
    }
}
