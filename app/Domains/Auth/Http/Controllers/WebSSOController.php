<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\SignIn;
use App\Domains\Core\Enums\ExternalService;
use App\Domains\Core\Exceptions\ServiceDownError;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\App;
use Northwestern\SysDev\SOA\Auth\Strategy\WebSSOStrategy;
use Northwestern\SysDev\SOA\Auth\WebSSOAuthentication;

class WebSSOController extends Controller
{
    use WebSSOAuthentication {
        oauthLogout as protected webSSOAuthOauthLogout;
    }

    protected const int RETRY_LOOKUP_TIMES = 3;

    protected const int RETRY_SLEEP_MS = 500;

    protected string $redirectTo = '/';

    public function __construct(
        protected SignIn $signIn,
    ) {
        $this->login_route_name = 'login-websso';
        $this->logout_return_to_route = 'filament.app.auth.login';
    }

    protected function findUserByNetID(FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory, ?string $netid = null): ?Authenticatable
    {
        /** @var ?Authenticatable */
        return retry(
            times: self::RETRY_LOOKUP_TIMES,
            callback: function () use ($findOrUpdateUserFromDirectory, $netid): User {
                $user = $findOrUpdateUserFromDirectory($netid);

                throw_unless(
                    $user,
                    ServiceDownError::class,
                    service: ExternalService::DirectorySearch,
                    additionalMessage: $findOrUpdateUserFromDirectory->getLastError(),
                    retryAttempted: self::RETRY_LOOKUP_TIMES
                );

                return $user;
            },
            sleepMilliseconds: static::RETRY_SLEEP_MS,
            when: fn (\Throwable $e) => ! ($e instanceof BadDirectoryEntry),
        );
    }

    protected function authenticated(Request $request, User $user): RedirectResponse
    {
        return $this->signIn->complete($user, $request, SignInMethod::Sso);
    }

    public function oauthLogout(?string $postLogoutRedirectUri = null): Application|RedirectResponse|Redirector
    {
        // CI has no identity provider to send people to.
        if (App::environment('ci')) {
            $this->signIn->endSession();

            return redirect()->route('filament.app.auth.login');
        }

        // The trait ends the session before sending the person to Entra ID's sign-out.
        return $this->webSSOAuthOauthLogout(route('filament.app.auth.login'));
    }

    /**
     * Override the trait's logout to also invalidate the Laravel session.
     */
    public function logout(WebSSOStrategy $ssoStrategy): RedirectResponse
    {
        $this->signIn->endSession();

        return $ssoStrategy->logout('filament.app.auth.login');
    }
}
