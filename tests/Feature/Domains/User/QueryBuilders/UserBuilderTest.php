<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\QueryBuilders;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Domains\User\QueryBuilders\UserBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserBuilder::class)]
class UserBuilderTest extends TestCase
{
    public function test_scope_sso_only_returns_sso_users(): void
    {
        $sso = User::factory()->create(['auth_type' => AuthTypeEnum::SSO]);
        User::factory()->create(['auth_type' => AuthTypeEnum::LOCAL]);
        User::factory()->create(['auth_type' => AuthTypeEnum::API]);

        $results = User::query()->sso()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($sso));
    }

    public function test_scope_local_only_returns_local_users(): void
    {
        User::factory()->create(['auth_type' => AuthTypeEnum::SSO]);
        $local = User::factory()->create(['auth_type' => AuthTypeEnum::LOCAL]);
        User::factory()->create(['auth_type' => AuthTypeEnum::API]);

        $results = User::query()->local()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($local));
    }

    public function test_scope_api_only_returns_api_users(): void
    {
        User::factory()->create(['auth_type' => AuthTypeEnum::SSO]);
        User::factory()->create(['auth_type' => AuthTypeEnum::LOCAL]);
        $api = User::factory()->create(['auth_type' => AuthTypeEnum::API]);

        $results = User::query()->api()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($api));
    }

    public function test_where_email_equals_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $found = User::query()->whereEmailEquals('TEST@Example.COM')->first();

        $this->assertTrue($user->is($found));
    }

    public function test_where_username_equals_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'jdoe']);

        $found = User::query()->whereUsernameEquals('JDOE')->first();

        $this->assertTrue($user->is($found));
    }

    public function test_search_by_name_matches_various_formats(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertTrue(User::query()->searchByName('John')->exists());
        $this->assertTrue(User::query()->searchByName('Doe')->exists());
        $this->assertTrue(User::query()->searchByName('John Doe')->exists());
        $this->assertTrue(User::query()->searchByName('Doe, John')->exists());
        $this->assertTrue(User::query()->searchByName('jo')->exists());
    }

    public function test_first_sso_by_email_returns_correct_user(): void
    {
        $sso = User::factory()->create([
            'email' => 'test@example.com',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        // Should not find this one
        User::factory()->create([
            'email' => 'other@example.com',
            'auth_type' => AuthTypeEnum::LOCAL,
        ]);

        $found = User::query()->firstSsoByEmail('TEST@example.com');

        $this->assertTrue($sso->is($found));
        $this->assertNull(User::query()->firstSsoByEmail('missing@example.com'));
    }

    public function test_find_local_by_email_returns_correct_user(): void
    {
        $local = User::factory()->create([
            'email' => 'local@example.com',
            'auth_type' => AuthTypeEnum::LOCAL,
        ]);

        $found = User::query()->firstLocalByEmail('LOCAL@example.com');

        $this->assertTrue($local->is($found));
    }

    public function test_first_by_email_sso_then_local_prioritizes_sso(): void
    {
        $ssoUser = User::factory()->create([
            'email' => 'sso@example.com',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $foundSso = User::query()->firstByEmailSsoThenLocal('sso@example.com');
        $this->assertTrue($foundSso->is($ssoUser));

        $localUser = User::factory()->create([
            'email' => 'local@example.com',
            'auth_type' => AuthTypeEnum::LOCAL,
        ]);

        $foundLocal = User::query()->firstByEmailSsoThenLocal('local@example.com');
        $this->assertTrue($foundLocal->is($localUser));
    }

    public function test_first_existing_by_email_or_new_sso_returns_existing(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $result = User::query()->firstExistingByEmailOrNewSso('EXISTING@example.com');

        $this->assertTrue($result->exists);
        $this->assertTrue($result->is($existing));
    }

    public function test_first_existing_by_email_or_new_sso_returns_new_instance(): void
    {
        $result = User::query()->firstExistingByEmailOrNewSso('new@example.com');

        $this->assertFalse($result->exists);
        $this->assertEquals('new@example.com', $result->email);
        $this->assertEquals(AuthTypeEnum::SSO, $result->auth_type);
    }

    public function test_first_existing_sso_by_netid_or_new_restores_trashed_user(): void
    {
        $trashed = User::factory()->create([
            'username' => 'trashed_user',
            'auth_type' => AuthTypeEnum::SSO,
            'deleted_at' => now(),
        ]);

        $this->assertTrue($trashed->trashed());

        $result = User::query()->firstExistingSsoByNetIdOrNew('TRASHED_USER');

        $this->assertTrue($result->exists);
        $this->assertEquals($trashed->id, $result->id);
        $this->assertFalse($result->trashed());
    }

    public function test_first_existing_sso_by_netid_or_new_returns_new_instance(): void
    {
        $result = User::query()->firstExistingSsoByNetIdOrNew('new_netid');

        $this->assertFalse($result->exists);
        $this->assertEquals('new_netid', $result->username);
        $this->assertEquals(AuthTypeEnum::SSO, $result->auth_type);
    }
}
