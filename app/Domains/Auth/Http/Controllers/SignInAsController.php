<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signs in as a seeded user without SSO, email or credentials, so a local environment works with
 * no Azure setup and agents can sign in with a URL. The route exists only where sign-in-as is
 * offered (AppPanelProvider), and this checks again in case it is ever registered elsewhere.
 */
class SignInAsController extends Controller
{
    public function __invoke(Request $request, string $username, SignIn $signIn): RedirectResponse
    {
        abort_unless($signIn->offers(SignInMethod::SignInAs), 404);

        $user = User::query()
            ->where('username', $username)
            ->where('auth_type', '!=', AuthType::API)
            ->firstOrFail();

        return $signIn->complete($user, $request, SignInMethod::SignInAs);
    }
}
