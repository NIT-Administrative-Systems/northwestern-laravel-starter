<?php

declare(strict_types=1);

namespace Database\Seeders\Sample;

use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * These users are used for end-to-end testing and can also be used for demos and impersonation during development.
 * As your application grows, you may want to seed additional users for specific roles or permissions.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->genericUser();
        $this->systemAdmin();

        if (config('auth.local.enabled')) {
            $this->localUser();
        }

        if (config('auth.api.enabled')) {
            $this->apiUser();
        }
    }

    private function localUser(): void
    {
        User::factory()
            ->affiliate()
            ->state([
                'username' => 'partner.user',
                'email' => 'partner-user@uchicago.edu',
                'first_name' => 'Partner',
                'last_name' => 'User',
                'job_titles' => ['Graduate Program Advisor'],
                'departments' => ['University of Chicago'],
                'description' => 'A local affiliate user from a partner institution.',
            ])
            ->createOne();
    }

    private function genericUser(): void
    {
        User::factory()
            ->state([
                'username' => 'generic.user',
                'email' => 'generic.user@northwestern.edu',
                'first_name' => 'Generic',
                'last_name' => 'User',
            ])
            ->createOne();
    }

    private function systemAdmin(): void
    {
        $user = User::factory()
            ->staff()
            ->state([
                'username' => 'nuit.admin',
                'email' => 'nuit.admin@northwestern.edu',
                'first_name' => 'NUIT',
                'last_name' => 'Administrator',
                'employee_id' => '9912991',
                'job_titles' => ['Developer'],
                'departments' => ['NUIT'],
            ])
            ->createOne();

        $user->roles()->attach(Role::whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SYSTEM_MANAGED))->firstOrFail());
    }

    private function apiUser(): void
    {
        $rawToken = config('auth.api.demo_user_token', Str::random(length: 64));

        User::factory()
            ->api()
            ->has(ApiToken::factory()->state([
                'token_prefix' => mb_substr($rawToken, 0, 5),
                'token_hash' => ApiToken::hashFromPlain($rawToken),
            ]), 'api_tokens')
            ->state([
                'username' => 'api-nuit',
                'description' => 'API user for demo and testing purposes.',
                'first_name' => 'NUIT',
                'last_name' => 'API',
                'email' => null,
                'employee_id' => null,
                'hr_employee_id' => null,
                'timezone' => 'America/Chicago',
            ])
            ->createOne();
    }
}
