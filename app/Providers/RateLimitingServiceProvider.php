<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Http\Responses\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.api.per_minute'))
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => ProblemDetails::tooManyRequests());
        });

        RateLimiter::for('auth:login-code:request', static function (Request $request) {
            $email = mb_strtolower(
                (string) $request->input(
                    key: 'email',
                    default: Session::get(LoginCodeSession::EMAIL, '')
                )
            );

            return [
                Limit::perMinute((int) config('rate-limiting.auth.login_code.request.per_minute'))->by($request->ip() ?? 'ip:none'),
                Limit::perMinute((int) config('rate-limiting.auth.login_code.request.per_email_per_minute'))->by('login-code:req:' . $email),
            ];
        });

        RateLimiter::for('auth:login-code:verify', static function (Request $request) {
            $ip = $request->ip() ?? 'ip:none';
            $encryptedChallengeId = Session::get(LoginCodeSession::CHALLENGE_ID);

            $challengeKey = $ip;
            if ($encryptedChallengeId) {
                try {
                    $challengeKey = Crypt::decryptString($encryptedChallengeId);
                } catch (DecryptException) {
                    //
                }
            }

            return [
                Limit::perMinute((int) config('rate-limiting.auth.login_code.verify.per_minute'))->by('login-code:verify:' . $ip),
                Limit::perMinute((int) config('rate-limiting.auth.login_code.verify.per_challenge_per_minute'))->by('login-code:verify:challenge:' . $challengeKey),
            ];
        });

        RateLimiter::for('auth:impersonate', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.auth.impersonate.per_minute'))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('support:contact', static function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute((int) config('rate-limiting.support.contact.per_minute'))->by('support:min:' . $key),
                Limit::perHour((int) config('rate-limiting.support.contact.per_hour'))->by('support:hr:' . $key),
                Limit::perDay((int) config('rate-limiting.support.contact.per_day'))->by('support:day:' . $key),
            ];
        });
    }
}
