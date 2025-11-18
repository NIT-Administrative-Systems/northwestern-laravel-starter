<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LoginSelectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(Request $request): RedirectResponse|View
    {
        if (config('auth.local.enabled')) {
            return view('auth.login-selection');
        }

        /**
         * Out of the box, the application only handles OAuth login. If your application requires additional login methods,
         * such as temporary logins for external users, you could alternatively redirect them to an interstitial login
         * selection page by analyzing a cookie set by the application to differentiate them from regular users.
         */
        if (App::environment('ci')) {
            return view('auth.login-selection');
        }

        return redirect(route('login-oauth-redirect'));
    }
}
