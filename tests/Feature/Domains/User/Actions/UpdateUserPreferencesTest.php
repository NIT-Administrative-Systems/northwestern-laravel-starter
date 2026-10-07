<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\UpdateUserPreferences;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UpdateUserPreferences::class)]
final class UpdateUserPreferencesTest extends TestCase
{
    public function test_it_saves_the_timezone_and_preferences(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);

        resolve(UpdateUserPreferences::class)($user, 'Europe/London', new UserPreferences(emailBeforeAccessTokensExpire: false));

        $user->refresh();
        $this->assertSame('Europe/London', $user->timezone);
        $this->assertFalse($user->preferences->emailBeforeAccessTokensExpire);
        $this->assertSame('{"emailBeforeAccessTokensExpire":false,"emailWhenApplicationConnects":true,"emailAnnouncements":true}', $user->getRawOriginal('preferences'));
    }

    public function test_it_refuses_a_value_that_is_not_a_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);

        try {
            resolve(UpdateUserPreferences::class)($user, 'Mars/Olympus_Mons', new UserPreferences());
            $this->fail('An unknown timezone was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame('America/Chicago', $user->refresh()->timezone);
        }
    }
}
