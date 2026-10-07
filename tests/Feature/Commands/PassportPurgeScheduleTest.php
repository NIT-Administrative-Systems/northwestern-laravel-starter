<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Northwestern\SysDev\Chassis\Passport\AccessRevoker;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

/**
 * The daily `passport:purge` in routes/console.php. Refresh tokens are found through their
 * access tokens, so an access token must outlive its refresh token's 30-day lifetime even
 * once it's revoked; deleting it sooner would hide a live refresh token from a disconnect.
 */
#[CoversNothing]
final class PassportPurgeScheduleTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    public function test_a_revoked_access_token_is_kept_while_its_refresh_token_can_still_be_found(): void
    {
        $person = User::factory()->create();
        $this->actingAs($person);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        $token = OAuthToken::query()->where('client_id', $client->getKey())->sole();
        $token->revoke();

        $this->runScheduledPurge();

        $this->assertTrue(OAuthToken::query()->whereKey($token->getKey())->exists());
        resolve(AccessRevoker::class)->revokeClient($person, $client);
        $this->assertTrue((bool) Passport::refreshToken()->newQuery()->where('access_token_id', $token->getKey())->value('revoked'));
    }

    public function test_tokens_expired_for_more_than_31_days_are_deleted(): void
    {
        $person = User::factory()->create();
        $this->actingAs($person);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        $token = OAuthToken::query()->where('client_id', $client->getKey())->sole();
        $token->forceFill(['revoked' => true, 'expires_at' => now()->subDays(32)])->save();

        $this->runScheduledPurge();

        $this->assertFalse(OAuthToken::query()->whereKey($token->getKey())->exists());
    }

    /**
     * Run `passport:purge` with the options it's scheduled with.
     */
    private function runScheduledPurge(): void
    {
        /** @var Event $event */
        $event = collect(resolve(Schedule::class)->events())
            ->sole(fn (Event $event): bool => str_contains((string) $event->command, 'passport:purge'));

        Artisan::call('passport:purge' . explode('passport:purge', (string) $event->command, 2)[1]);
    }
}
