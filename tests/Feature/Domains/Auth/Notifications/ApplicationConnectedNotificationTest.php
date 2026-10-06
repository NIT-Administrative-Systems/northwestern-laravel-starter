<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Notifications;

use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Notifications\ApplicationConnectedNotification;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(ApplicationConnectedNotification::class)]
final class ApplicationConnectedNotificationTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    public function test_it_is_always_shown_in_the_app_and_emailed_unless_turned_off(): void
    {
        $notification = new ApplicationConnectedNotification($this->connection());

        $this->assertSame(['database', 'mail'], $notification->via(User::factory()->create(['email' => 'willie@example.edu'])));
        $this->assertSame(['database'], $notification->via(User::factory()->create(['preferences' => new UserPreferences(emailWhenApplicationConnects: false)])));
        $this->assertSame(['database'], $notification->via(User::factory()->create(['email' => null])));
    }

    public function test_it_names_the_application_and_links_to_connected_applications(): void
    {
        $notification = new ApplicationConnectedNotification($this->connection());
        $user = User::factory()->create();

        $this->assertSame('Reporting Tool Connected to Your Account', $notification->toDatabase($user)['title']);
        $this->assertStringContainsString('/app/account/connected-applications', (string) $notification->toMail($user)->actionUrl);
    }

    public function test_the_person_finds_it_in_their_notifications(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);

        $this->assertSame('Reporting Tool Connected to Your Account', $user->notifications()->sole()->data['title']);
    }

    private function connection(): OAuthConnection
    {
        return (new OAuthConnection())->setRelation('oauth_client', $this->registerApplication());
    }
}
