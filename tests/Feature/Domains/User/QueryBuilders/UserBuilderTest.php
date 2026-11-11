<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\QueryBuilders;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use App\Domains\User\QueryBuilders\UserBuilder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserBuilder::class)]
final class UserBuilderTest extends TestCase
{
    public function test_scope_sso_only_returns_sso_users(): void
    {
        $sso = User::factory()->create(['auth_type' => AuthType::SSO]);
        User::factory()->create(['auth_type' => AuthType::Local]);
        User::factory()->create(['auth_type' => AuthType::API]);

        $results = User::query()->sso()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($sso));
    }

    public function test_scope_local_only_returns_local_users(): void
    {
        User::factory()->create(['auth_type' => AuthType::SSO]);
        $local = User::factory()->create(['auth_type' => AuthType::Local]);
        User::factory()->create(['auth_type' => AuthType::API]);

        $results = User::query()->local()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($local));
    }

    public function test_scope_api_only_returns_api_users(): void
    {
        User::factory()->create(['auth_type' => AuthType::SSO]);
        User::factory()->create(['auth_type' => AuthType::Local]);
        $api = User::factory()->create(['auth_type' => AuthType::API]);

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

    public function test_where_email_equals_ignores_surrounding_whitespace(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->assertTrue($user->is(User::query()->whereEmailEquals('  test@example.com ')->sole()));
    }

    public function test_where_email_equals_matches_non_ascii_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'école@example.com']);

        $this->assertTrue($user->is(User::query()->whereEmailEquals('ÉCOLE@EXAMPLE.COM')->sole()));
    }

    public function test_where_email_equals_lowercases_in_the_database_not_in_php(): void
    {
        // PHP's mb_strtolower turns İ into i with a combining dot, while the
        // database gives a plain i, so the database lowercases the value.
        //
        // The database's lowercase İ depends on the platform's
        // C library, and macOS keeps the dot, so this runs
        // only where it's a plain i, as on CI's Linux.
        if (DB::scalar("select lower('İ')") !== 'i') {
            $this->markTestSkipped("This database doesn't lowercase İ to a plain i.");
        }

        $user = User::factory()->create(['email' => 'iİ@example.com']);

        $this->assertTrue($user->is(User::query()->whereEmailEquals('İİ@EXAMPLE.COM')->sole()));
    }

    public function test_where_email_equals_treats_wildcards_as_literals(): void
    {
        User::factory()->create(['email' => 'axb@example.com']);
        $underscored = User::factory()->create(['email' => 'a_b@example.com']);

        $this->assertFalse(User::query()->whereEmailEquals('%@example.com')->exists());
        $this->assertFalse(User::query()->whereEmailEquals('a%')->exists());
        $this->assertTrue($underscored->is(User::query()->whereEmailEquals('a_b@example.com')->sole()));
    }

    public function test_where_username_equals_treats_wildcards_as_literals(): void
    {
        User::factory()->create(['username' => 'jdoe']);

        $this->assertFalse(User::query()->whereUsernameEquals('jd%')->exists());
        $this->assertFalse(User::query()->whereUsernameEquals('j_oe')->exists());
        $this->assertFalse(User::query()->whereUsernameEquals('%')->exists());
    }

    public function test_where_email_equals_works_alongside_a_join(): void
    {
        // The column is qualified, so a join with another email column isn't ambiguous.
        $user = User::factory()->create(['email' => 'joined@example.com']);

        $found = User::query()
            ->select('users.*')
            ->leftJoin('users as other', 'other.id', '=', 'users.id')
            ->whereEmailEquals('joined@example.com')
            ->sole();

        $this->assertTrue($user->is($found));
    }

    public function test_search_by_name_matches_various_formats(): void
    {
        User::factory()->create([
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
            'auth_type' => AuthType::SSO,
        ]);

        // Should not find this one
        User::factory()->create([
            'email' => 'other@example.com',
            'auth_type' => AuthType::Local,
        ]);

        $found = User::query()->firstSsoByEmail('TEST@example.com');

        $this->assertTrue($sso->is($found));
        $this->assertNull(User::query()->firstSsoByEmail('missing@example.com'));
    }

    public function test_find_local_by_email_returns_correct_user(): void
    {
        $local = User::factory()->create([
            'email' => 'local@example.com',
            'auth_type' => AuthType::Local,
        ]);

        $found = User::query()->firstLocalByEmail('LOCAL@example.com');

        $this->assertTrue($local->is($found));
    }

    public function test_first_by_email_sso_then_local_prioritizes_sso(): void
    {
        $ssoUser = User::factory()->create([
            'email' => 'sso@example.com',
            'auth_type' => AuthType::SSO,
        ]);

        $foundSso = User::query()->firstByEmailSsoThenLocal('sso@example.com');
        $this->assertTrue($foundSso->is($ssoUser));

        $localUser = User::factory()->create([
            'email' => 'local@example.com',
            'auth_type' => AuthType::Local,
        ]);

        $foundLocal = User::query()->firstByEmailSsoThenLocal('local@example.com');
        $this->assertTrue($foundLocal->is($localUser));
    }

    public function test_first_existing_by_email_or_new_sso_returns_existing(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'auth_type' => AuthType::SSO,
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
        $this->assertEquals(AuthType::SSO, $result->auth_type);
    }

    // Signing in or syncing never undoes a deactivation; only Reactivate does.
    public function test_first_existing_sso_by_netid_or_new_finds_a_trashed_user_without_restoring_it(): void
    {
        $trashed = User::factory()->create([
            'username' => 'trashed_user',
            'auth_type' => AuthType::SSO,
            'deleted_at' => now(),
        ]);

        $this->assertTrue($trashed->trashed());

        $result = User::query()->firstExistingSsoByNetIdOrNew('TRASHED_USER');

        $this->assertTrue($result->exists);
        $this->assertEquals($trashed->id, $result->id);
        $this->assertTrue($result->trashed());
        $this->assertSoftDeleted($trashed);
    }

    public function test_first_existing_sso_by_netid_or_new_returns_new_instance(): void
    {
        $result = User::query()->firstExistingSsoByNetIdOrNew('new_netid');

        $this->assertFalse($result->exists);
        $this->assertEquals('new_netid', $result->username);
        $this->assertEquals(AuthType::SSO, $result->auth_type);
    }
}
