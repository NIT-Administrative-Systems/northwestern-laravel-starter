<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Api;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Actions\Api\CreateApiUser;
use App\Domains\User\Enums\Affiliation;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateApiUser::class)]
final class CreateApiUserTest extends TestCase
{
    public function test_it_creates_an_api_user_with_its_first_client(): void
    {
        [$user, $secret, $client] = resolve(CreateApiUser::class)(
            username: 'API-Reporting',
            firstName: 'Reporting',
            clientName: 'Production Server',
            secretExpiresAt: now()->addDays(90),
            description: 'Nightly reporting',
            email: 'Team@Example.edu',
            allowedIps: ['127.0.0.1'],
        );

        $this->assertSame('api-reporting', $user->username);
        $this->assertSame(AuthType::API, $user->auth_type);
        $this->assertSame(Affiliation::Other, $user->primary_affiliation);
        $this->assertSame('team@example.edu', $user->email);
        $this->assertSame('API', $user->last_name);
        $this->assertSame('Nightly reporting', $user->description);

        $this->assertTrue($client->owner->is($user));
        $this->assertSame('Production Server', $client->name);
        $this->assertSame(['127.0.0.1'], $client->allowed_ips);
        $this->assertTrue(Hash::check($secret, $client->secret));
    }

    public function test_optional_details_can_be_left_out(): void
    {
        [$user, , $client] = resolve(CreateApiUser::class)(
            username: 'api-minimal',
            firstName: 'Minimal',
            clientName: 'Default',
            secretExpiresAt: now()->addDays(30),
        );

        $this->assertNull($user->email);
        $this->assertNull($user->description);
        $this->assertNull($client->allowed_ips);
    }
}
