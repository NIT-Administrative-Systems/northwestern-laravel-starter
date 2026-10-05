<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Actions\Applications\RegenerateOAuthApplicationSecret;
use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Auth\Actions\Applications\RevokeOAuthApplication;
use App\Domains\Auth\Actions\Applications\UpdateOAuthApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(RegisterOAuthApplication::class)]
#[CoversClass(UpdateOAuthApplication::class)]
#[CoversClass(RegenerateOAuthApplicationSecret::class)]
#[CoversClass(RevokeOAuthApplication::class)]
#[CoversClass(DisconnectApplication::class)]
final class OAuthApplicationActionsTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    public function test_a_confidential_application_gets_a_secret_and_a_public_one_does_not(): void
    {
        [$secret, $confidential] = resolve(RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, ['view-users'], description: 'Student portal', contactEmail: 'team@example.edu');
        [$noSecret, $public] = resolve(RegisterOAuthApplication::class)('Desktop', ['http://localhost/cb'], false, []);

        $this->assertTrue(Hash::check((string) $secret, (string) $confidential->secret));
        $this->assertSame(['authorization_code', 'refresh_token'], $confidential->grant_types);
        $this->assertSame(ClientOrigin::Administrator, $confidential->origin);
        $this->assertSame(['view-users'], $confidential->scopes);
        $this->assertSame('Student portal', $confidential->description);
        $this->assertNull($noSecret);
        $this->assertFalse($public->confidential());
    }

    public function test_updating_changes_the_details_scopes_and_consent(): void
    {
        $client = $this->registerApplication();

        resolve(UpdateOAuthApplication::class)($client, 'Renamed', ['https://new.example.edu/cb'], [], true, 'New', 'new@example.edu');

        $client->refresh();
        $this->assertSame('Renamed', $client->name);
        $this->assertSame(['https://new.example.edu/cb'], $client->redirect_uris);
        $this->assertSame([], $client->scopes);
        $this->assertTrue($client->first_party);
    }

    public function test_regenerating_replaces_a_confidential_secret(): void
    {
        [$old, $client] = resolve(RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, []);

        $new = resolve(RegenerateOAuthApplicationSecret::class)($client);

        $this->assertNotSame($old, $new);
        $this->assertTrue(Hash::check($new, (string) $client->fresh()?->secret));
    }

    public function test_a_public_application_has_no_secret_to_regenerate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        resolve(RegenerateOAuthApplicationSecret::class)($this->registerApplication());
    }

    public function test_revoking_an_application_disconnects_everyone(): void
    {
        $client = $this->registerApplication();
        $tokens = [];

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user);
            [, $verifier] = $this->requestAuthorization($client);
            $tokens[] = $this->exchange($client, $this->approve($client), $verifier)->json();
        }

        resolve(RevokeOAuthApplication::class)($client);

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
        $this->assertSame(0, OAuthConnection::query()->count());

        foreach ($tokens as $token) {
            $this->withToken($token['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
            $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => $token['refresh_token']])
                ->assertClientError()
                ->assertJsonStructure(['error']);
        }
    }

    // A personal disconnect never touches other people connected to the same application.
    public function test_disconnecting_leaves_other_people_connected(): void
    {
        $client = $this->registerApplication();
        $willie = User::factory()->create();
        $other = User::factory()->create();

        foreach ([$willie, $other] as $user) {
            $this->actingAs($user);
            [, $verifier] = $this->requestAuthorization($client);
            $tokens[$user->getKey()] = $this->exchange($client, $this->approve($client), $verifier)->json('access_token');
        }

        resolve(DisconnectApplication::class)(OAuthConnection::query()->where('user_id', $willie->getKey())->sole(), $willie);

        $this->withToken($tokens[$other->getKey()])->getJson('/api/v1/me')->assertOk();
        $this->assertSame([$other->getKey()], OAuthConnection::query()->pluck('user_id')->all());
        $this->assertFalse(Audit::query()->where('event', 'oauth_application_disconnected')->exists());
    }

    public function test_an_administrators_disconnect_is_audited_on_the_person(): void
    {
        $client = $this->registerApplication();
        $person = User::factory()->create();
        $this->actingAs($person);
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);

        resolve(DisconnectApplication::class)(OAuthConnection::query()->sole(), User::factory()->create());

        $audit = Audit::query()->where('event', 'oauth_application_disconnected')->sole();
        $this->assertSame($person->getKey(), $audit->auditable_id);
        $this->assertSame('Reporting Tool', $audit->new_values['application']);
    }

    public function test_disconnecting_is_refused_while_impersonating(): void
    {
        $client = $this->registerApplication();
        $person = User::factory()->create();
        $this->actingAs($person);
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);

        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        $this->expectException(AuthorizationException::class);

        resolve(DisconnectApplication::class)(OAuthConnection::query()->sole(), $person);
    }
}
