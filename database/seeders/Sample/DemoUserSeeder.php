<?php

declare(strict_types=1);

namespace Database\Seeders\Sample;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\Models\Role;
use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * These users are used for end-to-end testing and can also be used for demos and impersonation during development.
 * As your application grows, you may want to seed additional users for specific roles or permissions.
 */
class DemoUserSeeder extends Seeder
{
    /**
     * The users the sign-in page offers under "Sign in as" in local environments, in this order.
     * Add the users you seed for specific roles here so developers and agents can sign in as them.
     *
     * @var list<string>
     */
    public const array SIGN_IN_AS = [
        'nuit.admin',
        'generic.user',
        'partner.user',
    ];

    /**
     * The demo API user's service client in the `local` environment, fixed so local scripts and
     * agents can use the API across database rebuilds. Elsewhere the secret is random and
     * never shown; rotate the client in Administration to get one.
     */
    public const string DEMO_CLIENT_ID = '019a0000-0000-7000-8000-000000000001';

    public const string DEMO_CLIENT_SECRET = 'local-demo-client-secret';

    public function run(): void
    {
        $this->genericUser();
        $this->systemAdmin();

        if (resolve(SignIn::class)->offers(SignInMethod::EmailCode)) {
            $this->localUser();
        }

        if (config('api.enabled')) {
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

        $user->roles()->attach(Role::whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SystemManaged))->firstOrFail());
    }

    private function apiUser(): void
    {
        $user = User::factory()
            ->api()
            ->state([
                'username' => 'api-nuit',
                'description' => 'API user for demo and testing purposes.',
                'first_name' => 'NUIT',
                'last_name' => 'API',
                'email' => null,
                'employee_id' => null,
                'hr_employee_id' => null,
                'timezone' => config('platform.default_user_timezone'),
            ])
            ->createOne();

        [, $client] = resolve(CreateServiceClient::class)($user, 'Demo client', now()->addYear());

        if (App::isLocal()) {
            $client->forceFill(['id' => self::DEMO_CLIENT_ID, 'secret' => self::DEMO_CLIENT_SECRET])->save();
        }
    }
}
