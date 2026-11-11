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
 * Signs in as a seeded user with no SSO, email or credentials, so a local
 * environment works without Entra ID and agents can sign in from a URL.
 *
 * AppPanelProvider registers the route only where sign-in-as is offered,
 * and this checks again in case it is ever registered somewhere else.
 */
class SignInAsController extends Controller
{
    public function __invoke(Request $request, string $username, SignIn $signIn): RedirectResponse
    {
        abort_unless($signIn->offers(SignInMethod::SignInAs), 404);

        // Deactivated accounts too: SignIn::complete() refuses them, as for every method.
        $user = User::withTrashed()
            ->where('username', $username)
            ->where('auth_type', '!=', AuthType::API)
            ->firstOrFail();

        return $signIn->complete($user, $request, SignInMethod::SignInAs);
    }
}
