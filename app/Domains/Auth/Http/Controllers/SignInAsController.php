<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Signs in as a seeded user without SSO, email or credentials, so a local environment works with
 * no Azure setup and agents can sign in with a URL. The route exists only in the `local`
 * environment (AppPanelProvider), and this checks again in case it is ever registered elsewhere.
 */
class SignInAsController extends Controller
{
    public function __invoke(Request $request, string $username, RecordLogin $recordLogin): RedirectResponse
    {
        abort_unless(App::environment('local'), 404);

        $user = User::query()
            ->where('username', $username)
            ->where('auth_type', '!=', AuthType::API)
            ->firstOrFail();

        Auth::login($user);
        Session::regenerate();
        Session::regenerateToken();

        $recordLogin($user, $request);

        // Every sign-in path lands on `/`, where HomeController decides where signed-in users go.
        return redirect()->intended('/');
    }
}
