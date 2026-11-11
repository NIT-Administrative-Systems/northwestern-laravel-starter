<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Http\Controllers\Controller;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ImpersonationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only(['take']);
    }

    /** {@see \Lab404\Impersonate\Controllers\ImpersonateController::take()} */
    public function take(Request $request, StartImpersonation $startImpersonation, string|int $id, ?string $guardName = null): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(filled($user), 403);
        abort_unless($user->can(SystemPermission::ManageImpersonation), 403);

        $this->storeReturnUrl($request);

        $redirectTo = $startImpersonation(
            user: $user,
            userIdToImpersonate: $id,
            guardName: $guardName,
        );

        // The page the administrator came from is usually one the person being impersonated
        // can't open, so start them in the app panel; the stored URL is for leaving.
        if ($redirectTo === '/') {
            return redirect()->to(Filament::getPanel(AppPanelProvider::ID)->getUrl());
        }

        return $this->resolveRedirect($redirectTo);
    }

    /** {@see \Lab404\Impersonate\Controllers\ImpersonateController::leave()} */
    public function leave(StopImpersonation $stopImpersonation): RedirectResponse
    {
        return $this->resolveRedirect($stopImpersonation());
    }

    /**
     * Store the referer URL in session if it belongs to the same domain.
     *
     * Used when impersonation ends to return to the page the admin was viewing.
     */
    private function storeReturnUrl(Request $request): void
    {
        $returnUrl = $request->headers->get('referer');

        if (! $returnUrl || ! filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        $refererHost = parse_url($returnUrl, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($refererHost === $appHost) {
            Session::put('impersonation.return_url', $returnUrl);
        }
    }

    private function resolveRedirect(string $redirectTo): RedirectResponse
    {
        if ($redirectTo === 'back') {
            return redirect()->back();
        }

        return redirect()->to($redirectTo);
    }
}
