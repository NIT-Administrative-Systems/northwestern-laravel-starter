<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Domains\Auth\Enums\SystemPermission;
use App\Http\Controllers\Controller;
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
     * Used after impersonation starts to redirect back to the page the admin was viewing.
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

    /**
     * Resolve the redirect target, preferring the stored return URL over the default root.
     */
    private function resolveRedirect(string $redirectTo): RedirectResponse
    {
        if ($redirectTo === 'back') {
            return redirect()->back();
        }

        $returnUrl = Session::get('impersonation.return_url');

        if ($redirectTo === '/' && $returnUrl) {
            return redirect()->to($returnUrl);
        }

        return redirect()->to($redirectTo);
    }
}
