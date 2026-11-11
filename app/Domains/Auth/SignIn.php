<?php

declare(strict_types=1);

namespace App\Domains\Auth;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\Enums\SsoProvider;
use App\Domains\Auth\Exceptions\AccountDeactivated;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * How people sign in and out: the methods this application offers,
 * the step every method ends with, and where signing out leads.
 *
 * The sign-in page, the panel's routes and the actions for
 * local accounts in Administration ask {@see offers()}.
 *
 * SSO, sign-in-as and the email code page all finish
 * with {@see complete()}, so every sign-in starts
 * a fresh session and is recorded the same way.
 *
 * Signing out goes through {@see signOut()}, and anything else ending a session,
 * such as switching account on the consent screen, uses {@see endSession()}.
 */
final readonly class SignIn
{
    public function __construct(
        private RecordLogin $recordLogin,
    ) {
        //
    }

    /**
     * Get the methods this application offers, in the sign-in page's order.
     *
     * Single sign-on when a provider is set up, email codes when `local-auth.enabled`
     * is on, and sign-in-as in `local` alone: an allowlist, not "not production".
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
     * Get the URL that starts signing in with `$method`, or null when it
     * isn't offered. Sign-in-as has one per seeded user, so none here.
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
     * Sign `$user` in once their method has verified them, and send them to
     * the page they asked for, or to `/`, where HomeController decides.
     *
     * The new session and CSRF token make a session fixed before sign-in worthless.
     *
     * Every sign-in is recorded, and a deactivated account is refused.
     *
     *
     * @throws AccountDeactivated
     */
    public function complete(User $user, Request $request, SignInMethod $method): RedirectResponse
    {
        // Single sign-on has signed them in by now, so sign a deactivated account out.
        if ($user->trashed()) {
            $this->endSession();

            throw new AccountDeactivated();
        }

        Auth::guard('web')->login($user, remember: $method->remembers());
        Session::regenerate();
        Session::regenerateToken();

        ($this->recordLogin)($user, $request);

        return redirect()->intended('/');
    }

    /**
     * Sign the person out the way they signed in: through
     * the identity provider for single sign-on, so its
     * session ends too, or here for a local account.
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
