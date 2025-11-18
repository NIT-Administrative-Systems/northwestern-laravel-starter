<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(route('login-selection'));
        }

        return redirect(route('login-oauth-logout'));
    }
}
