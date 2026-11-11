<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions;

use App\Domains\Auth\Actions\EndSessions;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EndSessions::class)]
final class EndSessionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_it_deletes_the_persons_sessions_and_no_one_elses(): void
    {
        $person = User::factory()->create();
        $someoneElse = User::factory()->create();
        $this->storedSession('one', $person);
        $this->storedSession('two', $person);
        $this->storedSession('three', $someoneElse);
        $this->storedSession('guest', null);

        resolve(EndSessions::class)($person);

        $this->assertSame(['guest', 'three'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
    }

    // A "remember me" cookie holds the old token, so it no longer signs them back in.
    public function test_it_cycles_the_remember_token(): void
    {
        $person = User::factory()->create(['remember_token' => 'old-token']);

        resolve(EndSessions::class)($person);

        $this->assertNotSame('old-token', $person->fresh()?->getRememberToken());
        $this->assertSame(60, strlen((string) $person->fresh()?->getRememberToken()));
    }

    public function test_sessions_in_another_driver_are_left_to_expire(): void
    {
        config(['session.driver' => 'array']);
        $person = User::factory()->create(['remember_token' => 'old-token']);
        $this->storedSession('one', $person);

        resolve(EndSessions::class)($person);

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertNotSame('old-token', $person->fresh()?->getRememberToken());
    }

    private function storedSession(string $id, ?User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user?->getKey(),
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
