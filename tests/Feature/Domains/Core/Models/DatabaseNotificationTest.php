<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models;

use App\Domains\Core\Models\DatabaseNotification;
use App\Domains\User\Models\User;
use Filament\Notifications\Notification;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class DatabaseNotificationTest extends TestCase
{
    public function test_a_persons_notifications_use_the_prunable_model(): void
    {
        $person = User::factory()->create();

        Notification::make()->title('Hello')->sendToDatabase($person);

        $this->assertInstanceOf(DatabaseNotification::class, $person->notifications()->sole());
    }

    public function test_notifications_are_pruned_after_ninety_days_by_default_read_or_not(): void
    {
        $person = User::factory()->create();

        $this->travel(-91)->days();
        Notification::make()->title('Old')->sendToDatabase($person);
        $this->travelBack();
        $this->travel(-89)->days();
        Notification::make()->title('Recent')->sendToDatabase($person);
        $this->travelBack();

        $this->artisan('model:prune', ['--model' => [DatabaseNotification::class]])->assertSuccessful();

        $this->assertSame(['Recent'], $person->notifications()->get()->pluck('data.title')->all());
    }

    public function test_null_keeps_notifications(): void
    {
        $person = User::factory()->create();
        $this->travel(-400)->days();
        Notification::make()->title('Old')->sendToDatabase($person);
        $this->travelBack();

        config(['platform.retention.notifications' => null]);
        $this->artisan('model:prune', ['--model' => [DatabaseNotification::class]])->assertSuccessful();

        $this->assertCount(1, $person->notifications()->get());
    }
}
