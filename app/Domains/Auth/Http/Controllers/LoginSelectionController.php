<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

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
         * In the CI environment (e.g. GitHub Actions), we show the login selection page
         * instead of redirecting to Azure AD, since CI doesn't have Azure credentials
         * and cannot perform real OAuth logins.
         *
         * This ensures that frontend tests or CI-driven browser interactions can reach
         * the login page without triggering external auth flows.
         */
        if (App::environment('ci')) {
            return view('auth.login-selection');
        }

        return redirect(route('login-oauth-redirect'));
    }
}
