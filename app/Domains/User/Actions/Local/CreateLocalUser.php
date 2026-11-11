<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Local;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\LoginCodes;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a local account for someone outside Northwestern.
 *
 * Local accounts sign in with a one-time code sent to their email. Only the email
 * is required: people added in bulk start with whatever the list gave for them.
 */
readonly class CreateLocalUser
{
    public function __invoke(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $title = null,
        ?string $department = null,
        ?string $description = null,
        bool $sendLoginLink = true,
    ): User {
        $user = retry(3, fn () => DB::transaction(function () use ($email, $firstName, $lastName, $title, $department, $description) {
            $username = $this->generateUsername($email);

            return User::create([
                'username' => $username,
                'auth_type' => AuthType::Local,
                'primary_affiliation' => Affiliation::Affiliate,
                'email' => strtolower($email),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'job_titles' => filled($title) ? [$title] : [],
                'departments' => filled($department) ? [$department] : [],
                'description' => $description,
                'timezone' => config('platform.default_user_timezone'),
            ]);
        }), 0, fn (Throwable $e) => $e instanceof UniqueConstraintViolationException);

        if ($sendLoginLink) {
            try {
                resolve(LoginCodes::class)->issueFor($user, request());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $user;
    }

    /**
     * Generate an unused username: the email's local part and six random characters.
     */
    private function generateUsername(string $email): string
    {
        $baseUsername = Str::slug(Str::before(strtolower($email), '@'));

        do {
            $username = $baseUsername . '-' . Str::random(length: 6);
        } while (User::query()->where('username', $username)->exists());

        return $username;
    }
}
