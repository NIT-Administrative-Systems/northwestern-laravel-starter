<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Personal;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use App\Providers\OAuthServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;
use RuntimeException;

/**
 * Creates a personal access token: a person's own credential for calling the API from a
 * script, limited to scopes their permissions cover and to a lifetime they choose.
 */
readonly class CreatePersonalAccessToken
{
    public function __construct(
        private ClientRepository $clients,
    ) {
    }

    /**
     * The scopes this person may give a token: one per API-relevant permission they hold.
     *
     * @return array<string, string> Scope name => description
     */
    public static function scopesFor(User $user): array
    {
        return array_filter(
            OAuthServiceProvider::scopes(),
            fn (string $scope): bool => $user->can(SystemPermission::from($scope)),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param  non-empty-string  $name  What the token is for, e.g. "Nightly export script"
     * @param  list<string>  $scopes
     * @return array{0: non-empty-string, 1: OAuthToken} The bearer token, shown once, and its record
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, string $name, array $scopes, TokenExpiration $lifetime): array
    {
        if ($user->auth_type === AuthType::API || ! $user->can(SystemPermission::CreatePersonalAccessTokens)) {
            throw new AuthorizationException('You are not allowed to create personal access tokens.');
        }

        if ($user->isImpersonated()) {
            throw new AuthorizationException('Personal access tokens cannot be created while impersonating.');
        }

        if (! in_array($lifetime, TokenExpiration::forPersonalAccessTokens((int) config('api.personal_access_tokens.max_lifetime_days')), true)) {
            throw new InvalidArgumentException("A personal access token cannot last {$lifetime->getLabel()}.");
        }

        if (array_diff($scopes, array_keys(self::scopesFor($user))) !== []) {
            throw new InvalidArgumentException('A personal access token can only have scopes your permissions cover.');
        }

        if (OAuthToken::query()->personal()->active()->where('user_id', $user->getKey())->count() >= (int) config('api.personal_access_tokens.max_active')) {
            throw new InvalidArgumentException('You have reached the maximum number of active personal access tokens. Revoke one to create another.');
        }

        $this->ensurePersonalAccessClient();

        return DB::transaction(function () use ($user, $name, $scopes, $lifetime): array {
            $result = $user->createToken($name, array_values($scopes));

            // Passport builds the token from the model registered in OAuthServiceProvider. Not an
            // instanceof check: Rector misreads that one as always failing and deletes the code after it.
            /** @var OAuthToken|null $token */
            $token = $result->getToken();

            if ($token === null) {
                throw new RuntimeException('Passport did not return the token it created.');
            }

            // The token is signed with Passport's single maximum lifetime; this shorter expiry is
            // enforced by ExpiringAccessTokenRepository on every request.
            $token->forceFill(['expires_at' => $lifetime->expiresAt()])->save();

            /** @var non-empty-string $accessToken */
            $accessToken = $result->accessToken;

            return [$accessToken, $token];
        });
    }

    /**
     * Passport issues personal access tokens through a personal access client. Create it the
     * first time it's needed, so no environment has to run `passport:client --personal`.
     */
    private function ensurePersonalAccessClient(): void
    {
        $provider = (string) config('auth.guards.api.provider');

        try {
            $this->clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $this->clients->createPersonalAccessGrantClient('Personal Access Client', $provider);
        }
    }
}
