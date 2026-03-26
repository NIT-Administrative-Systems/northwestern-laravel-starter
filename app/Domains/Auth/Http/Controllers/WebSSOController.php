<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Core\Enums\ExternalService;
use App\Domains\Core\Exceptions\ServiceDownError;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
        protected RecordLogin $recordLogin,
    ) {
        $this->login_route_name = 'login-websso';
        $this->logout_return_to_route = 'login-selection';
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

    /**
     * @param  User  $user
     */
    protected function authenticated(Request $request, $user): void
    {
        Session::regenerate();
        Session::regenerateToken();

        ($this->recordLogin)($user, $request);

        // Return a redirect here if you have a use for that functionality.
    }

    public function oauthLogout(?string $postLogoutRedirectUri = null): Application|RedirectResponse|Redirector
    {
        if (App::environment('ci')) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            return redirect()->route('login-selection');
        }

        $response = $this->webSSOAuthOauthLogout(route('login-selection'));
        Session::invalidate();
        Session::regenerateToken();

        return $response;
    }

    /**
     * Override the trait's logout to also invalidate the Laravel session.
     */
    public function logout(WebSSOStrategy $ssoStrategy): RedirectResponse
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        return $ssoStrategy->logout('login-selection');
    }
}
