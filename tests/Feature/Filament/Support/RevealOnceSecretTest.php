<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support;

use App\Domains\User\Models\User;
use App\Filament\Support\RevealOnceSecret;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The wizards' show-once rules, at the one interface every credential wizard uses. It's under
 * app/Filament, outside the coverage gate, so these tests hold it.
 */
#[CoversNothing]
final class RevealOnceSecretTest extends TestCase
{
    public function test_it_issues_once_per_run(): void
    {
        $secret = RevealOnceSecret::for('test:issue');
        $issued = 0;

        foreach ([1, 2] as $_) {
            $secret->issueOnce(function () use (&$issued): array {
                $issued++;

                return ['id' => 'client-1', 'secret' => 'shh', 'user_id' => 7];
            });
        }

        $this->assertSame(1, $issued);
        $this->assertTrue($secret->issued());
        $this->assertSame('client-1', $secret->identifier());
        $this->assertSame('shh', $secret->secret());
        $this->assertSame(7, $secret->get('user_id'));
    }

    // Never in plain text, even in the session.
    public function test_the_secret_is_kept_encrypted(): void
    {
        RevealOnceSecret::for('test:encrypted')->issueOnce(fn (): array => ['id' => 1, 'secret' => 'plain-secret']);

        $this->assertStringNotContainsString('plain-secret', json_encode(Session::all(), JSON_THROW_ON_ERROR));
    }

    // A rotation's secret shows only on the client being rotated.
    public function test_a_scoped_secret_shows_only_on_its_record(): void
    {
        $rotating = User::factory()->create();
        $other = User::factory()->create();
        $secret = RevealOnceSecret::for('test:scoped');

        $secret->issueOnce(fn (): array => ['id' => 'replacement', 'secret' => 'shh'], scope: $rotating);

        $this->assertTrue($secret->isFor($rotating));
        $this->assertFalse($secret->isFor($other));
        $this->assertSame('shh', $secret->secret($rotating));
        $this->assertNull($secret->secret($other));
        $this->assertNull($secret->identifier());
    }

    // A cancelled run leaves its secret behind until the next one mounts.
    public function test_mounting_and_forgetting_leave_nothing_to_show(): void
    {
        $secret = RevealOnceSecret::for('test:fresh');
        $secret->issueOnce(fn (): array => ['id' => 1, 'secret' => 'abandoned']);

        ($secret->mountFresh())(null);

        $this->assertFalse($secret->issued());
        $this->assertNull($secret->secret());

        $secret->issueOnce(fn (): array => ['id' => 2, 'secret' => 'copied']);
        $secret->forget();

        $this->assertFalse($secret->issued());
    }

    public function test_a_credential_without_a_secret_and_other_wizards_show_nothing(): void
    {
        $public = RevealOnceSecret::for('test:public');
        $public->issueOnce(fn (): array => ['id' => 'public-app', 'secret' => null]);

        $this->assertSame('public-app', $public->identifier());
        $this->assertNull($public->secret());
        $this->assertFalse(RevealOnceSecret::for('test:other')->issued());
        $this->assertNull(RevealOnceSecret::for('test:other')->get('id'));
    }
}
