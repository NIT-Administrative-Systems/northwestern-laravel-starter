<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LogoutSelectionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        /** @var ?User $user */
        $user = $request->user();

        if (! $user) {
            return redirect(route('login-selection'));
        }

        if ($user->auth_type === AuthTypeEnum::LOCAL) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            return redirect(route('login-selection'));
        }

        $webssoConfigured = filled(config('nusoa.sso.apigeeApiKey'))
            || config('nusoa.sso.strategy') === 'forgerock-direct';

        return redirect(
            route(
                $webssoConfigured
                ? 'login-websso-logout'
                : 'login-oauth-logout'
            )
        );
    }
}
