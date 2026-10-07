<?php

declare(strict_types=1);

namespace App\Domains\Auth;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\Enums\SsoProvider;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * How people sign in and out: which methods this application offers, the one step every
 * method ends with, and where signing out goes.
 *
 * - The sign-in page, the panel's routes and the administrators' local-account actions ask
 *   {@see offers()}.
 * - The SSO controller, sign-in-as and the email code page finish with {@see complete()}, so
 *   every sign-in starts a fresh session and is recorded the same way.
 * - Signing out goes through {@see signOut()}, and anything else that ends a session, such as
 *   switching account on the consent screen, through {@see endSession()}.
 */
final readonly class SignIn
{
    public function __construct(
        private RecordLogin $recordLogin,
    ) {
        //
    }

    /**
     * The methods this application offers, in the order the sign-in page lists them: single
     * sign-on when a provider is configured, email codes when `local-auth.enabled` is on, and
     * sign-in-as in the `local` environment only, an explicit allowlist rather than "not production".
     *
     * @return list<SignInMethod>
     */
    public function methods(): array
    {
        return array_values(array_filter([
            SsoProvider::configured() instanceof SsoProvider ? SignInMethod::Sso : null,
            config('local-auth.enabled') ? SignInMethod::EmailCode : null,
            App::environment('local') ? SignInMethod::SignInAs : null,
        ]));
    }

    public function offers(SignInMethod $method): bool
    {
        return in_array($method, $this->methods(), true);
    }

    /**
     * Where a person starts signing in with `$method`, or null when it isn't offered. Sign-in-as
     * has one address per seeded user, so none here.
     */
    public function url(SignInMethod $method): ?string
    {
        $provider = SsoProvider::configured();

        return match (true) {
            ! $this->offers($method) => null,
            $method === SignInMethod::Sso && $provider instanceof SsoProvider => route($provider->loginRoute()),
            $method === SignInMethod::EmailCode => route('filament.app.auth.login-code'),
            default => null,
        };
    }

    /**
     * Signs `$user` in once their method has verified them: a new session and CSRF token, so a
     * session fixed before sign-in is worthless, a login record, and the page they asked for or `/`,
     * where HomeController decides where signed-in people go.
     */
    public function complete(User $user, Request $request, SignInMethod $method): RedirectResponse
    {
        Auth::guard('web')->login($user, remember: $method->remembers());
        Session::regenerate();
        Session::regenerateToken();

        ($this->recordLogin)($user, $request);

        return redirect()->intended('/');
    }

    /**
     * Signs the person out the way they signed in: through the identity provider for single
     * sign-on, so its session ends too, or here for a local account.
     */
    public function signOut(Request $request): RedirectResponse
    {
        $user = $request->user();
        $provider = SsoProvider::configured();

        if ($user instanceof User && $user->auth_type !== AuthType::Local && $provider instanceof SsoProvider) {
            return redirect()->route($provider->logoutRoute());
        }

        $this->endSession();

        return redirect()->route('filament.app.auth.login');
    }

    /**
     * Signs out of this application only, with a new session and CSRF token.
     */
    public function endSession(): void
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
    }
}
