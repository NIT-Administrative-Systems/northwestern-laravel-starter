<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Local;

use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new local user account for external (non-Northwestern) users.
 *
 * Local users authenticate via one-time verification codes sent to their email.
 */
readonly class CreateLocalUser
{
    public function __invoke(
        string $email,
        string $firstName,
        string $lastName,
        string $title,
        string $department,
        ?string $description = null,
        bool $sendLoginLink = true,
    ): User {
        $user = DB::transaction(function () use ($email, $firstName, $lastName, $title, $department, $description) {
            $username = $this->generateUsername($email);

            return User::create([
                'username' => $username,
                'auth_type' => AuthTypeEnum::LOCAL,
                'primary_affiliation' => AffiliationEnum::AFFILIATE,
                'email' => strtolower($email),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'job_titles' => [$title],
                'departments' => [$department],
                'description' => $description,
            ]);
        });

        if ($sendLoginLink) {
            resolve(IssueLoginChallenge::class)($user->email, request()->ip(), request()->userAgent());
        }

        return $user;
    }

    /**
     * Generate a unique username from email address.
     * Format: email-{random} to ensure uniqueness
     */
    private function generateUsername(string $email): string
    {
        $baseUsername = Str::before(strtolower($email), '@');
        $baseUsername = Str::slug($baseUsername);

        // Ensure uniqueness by appending random string
        do {
            $username = $baseUsername . '-' . Str::random(length: 6);
        } while (User::where('username', $username)->exists());

        return $username;
    }
}
