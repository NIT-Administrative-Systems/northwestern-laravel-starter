<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnvironmentLockdown;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Displays a lockdown page for authenticated users without assigned roles beyond Northwestern User.
 *
 * This controller is typically used in non-production environments to prevent
 * users who discover the application URL from accessing it without proper role
 * assignment. Users with only the default Northwestern User role (or no roles)
 * see a message explaining they need to be granted access by an administrator.
 *
 * @see EnvironmentLockdown
 */
class EnvironmentLockdownController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->non_default_roles->isNotEmpty()) {
            return to_route('home');
        }

        return view('platform.environment-lockdown');
    }
}
