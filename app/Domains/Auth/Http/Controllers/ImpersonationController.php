<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Domains\Auth\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        abort_unless($user->can(PermissionEnum::MANAGE_IMPERSONATION), 403);

        // Validate that the URL is from the same domain
        $returnUrl = $request->headers->get('referer');
        if ($returnUrl && filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($returnUrl);
            $currentHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (isset($parsedUrl['host']) && $parsedUrl['host'] === $currentHost) {
                session()->put('impersonation.return_url', $returnUrl);
            }
        }

        $redirectTo = $startImpersonation(
            user: $user,
            userIdToImpersonate: $id,
            guardName: $guardName,
        );

        // If the redirect is 'back' or we want to stay on the current page
        if ($redirectTo === 'back') {
            return redirect()->back();
        }

        // If it's the default root redirect ('/'), stay on the current page instead
        if ($redirectTo === '/' && $returnUrl) {
            return redirect()->to($returnUrl);
        }

        // Otherwise use the specified redirect
        return redirect()->to($redirectTo);
    }

    /** {@see \Lab404\Impersonate\Controllers\ImpersonateController::leave()} */
    public function leave(StopImpersonation $stopImpersonation): RedirectResponse
    {
        $redirectTo = $stopImpersonation();

        if ($redirectTo !== 'back') {
            return redirect()->to($redirectTo);
        }

        return redirect()->back();
    }
}
