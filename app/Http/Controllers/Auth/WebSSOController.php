<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Core\Enums\ExternalServiceEnum;
use App\Domains\Core\Exceptions\ServiceDownError;
use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Actions\Directory\CreateUserByLookup;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Northwestern\SysDev\SOA\Auth\WebSSOAuthentication;

class WebSSOController extends Controller
{
    use WebSSOAuthentication;

    /**
     * @var int
     */
    protected const RETRY_LOOKUP_TIMES = 3;

    protected string $redirectTo = '/';

    use WebSSOAuthentication {
        oauthLogout as protected webSSOAuthOauthLogout;
    }

    public function __construct(
        protected DetermineUserSegment $determineUserSegment,
    ) {
        //
    }

    protected function findUserByNetID(CreateUserByLookup $createByLookup, ?string $netid = null): ?Authenticatable
    {
        return retry(
            times: self::RETRY_LOOKUP_TIMES,
            callback: function () use ($createByLookup, $netid): User {
                $user = $createByLookup($netid);

                throw_unless($user, new ServiceDownError(
                    service: ExternalServiceEnum::DIRECTORY_SEARCH,
                    additionalMessage: $createByLookup->getLastError(),
                    retryAttempted: self::RETRY_LOOKUP_TIMES,
                ));

                return $user;
            },
            sleepMilliseconds: 500
        );
    }

    /**
     * @param  User  $user
     */
    protected function authenticated(Request $request, $user): void
    {
        // Potentially expire a cookie used to differentiate between external users and internal users.

        $user->login_records()->create([
            'logged_in_at' => Carbon::now(),
            'segment' => ($this->determineUserSegment)($user),
        ]);

        // Return a redirect here if you have a use for that functionality.
    }

    public function oauthLogout(?string $postLogoutRedirectUri = null): Application|RedirectResponse|Redirector
    {
        if (App::environment('ci')) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('login-selection');
        }

        return $this->webSSOAuthOauthLogout(route('login-selection'));
    }
}
