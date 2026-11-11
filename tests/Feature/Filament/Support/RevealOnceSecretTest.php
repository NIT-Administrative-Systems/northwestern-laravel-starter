<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\AccessTokens;
use App\Filament\Support\RevealOnceSecret;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The wizards' show-once rules, at the one interface every credential wizard uses.
 * It's outside the coverage gate, under app/Filament, so these tests cover it.
 *
 * Each run is a credential wizard mounted on a
 * page, which is what gives it its own key.
 */
#[CoversNothing]
final class RevealOnceSecretTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);
        $this->actingAs($user);
    }

    public function test_it_issues_once_per_run(): void
    {
        $run = $this->mountWizard();
        $secret = RevealOnceSecret::for('test:issue');
        $issued = 0;

        foreach ([1, 2] as $_) {
            $secret->issueOnce($run, function () use (&$issued): array {
                $issued++;

                return ['id' => 'client-1', 'secret' => 'shh', 'user_id' => 7];
            });
        }

        $this->assertSame(1, $issued);
        $this->assertTrue($secret->issued($run));
        $this->assertSame('client-1', $secret->identifier($run));
        $this->assertSame('shh', $secret->secret($run));
        $this->assertSame(7, $secret->get($run, 'user_id'));
    }

    // Two runs at once, as in two browser tabs: each issues and sees only its own.
    public function test_each_run_keeps_its_own_secret(): void
    {
        $first = $this->mountWizard();
        $second = $this->mountWizard();
        $secret = RevealOnceSecret::for('test:runs');

        $secret->issueOnce($first, fn (): array => ['id' => 'first', 'secret' => 'one']);
        $secret->issueOnce($second, fn (): array => ['id' => 'second', 'secret' => 'two']);

        $this->assertSame('one', $secret->secret($first));
        $this->assertSame('two', $secret->secret($second));

        $secret->forget($first);

        $this->assertFalse($secret->issued($first));
        $this->assertSame('two', $secret->secret($second));
    }

    // Never in plain text, even in the session.
    public function test_the_secret_is_kept_encrypted(): void
    {
        RevealOnceSecret::for('test:encrypted')->issueOnce($this->mountWizard(), fn (): array => ['id' => 1, 'secret' => 'plain-secret']);

        $this->assertStringNotContainsString('plain-secret', json_encode(Session::all(), JSON_THROW_ON_ERROR));
    }

    // A rotation's secret shows only on the client being rotated.
    public function test_a_scoped_secret_shows_only_on_its_record(): void
    {
        $run = $this->mountWizard();
        $rotating = User::factory()->create();
        $other = User::factory()->create();
        $secret = RevealOnceSecret::for('test:scoped');

        $secret->issueOnce($run, fn (): array => ['id' => 'replacement', 'secret' => 'shh'], scope: $rotating);

        $this->assertTrue($secret->isFor($run, $rotating));
        $this->assertFalse($secret->isFor($run, $other));
        $this->assertSame('shh', $secret->secret($run, $rotating));
        $this->assertNull($secret->secret($run, $other));
        $this->assertNull($secret->identifier($run));
    }

    // An ended run, or a component with no run mounted, has nothing to show or issue.
    public function test_without_a_run_nothing_is_issued_or_shown(): void
    {
        $page = Livewire::test(AccessTokens::class)->instance();
        $this->assertInstanceOf(AccessTokens::class, $page);
        $secret = RevealOnceSecret::for('test:fresh');
        $issued = false;

        $secret->issueOnce($page, function () use (&$issued): array {
            $issued = true;

            return ['id' => 1, 'secret' => 'never'];
        });
        $secret->forget($page);

        $this->assertFalse($issued);
        $this->assertFalse($secret->issued($page));
        $this->assertNull($secret->secret($page));
    }

    public function test_a_credential_without_a_secret_and_other_wizards_show_nothing(): void
    {
        $run = $this->mountWizard();
        $public = RevealOnceSecret::for('test:public');
        $public->issueOnce($run, fn (): array => ['id' => 'public-app', 'secret' => null]);

        $this->assertSame('public-app', $public->identifier($run));
        $this->assertNull($public->secret($run));
        $this->assertFalse(RevealOnceSecret::for('test:other')->issued($run));
        $this->assertNull(RevealOnceSecret::for('test:other')->get($run, 'id'));
    }

    /**
     * A page with a credential wizard just mounted, so it holds a new run.
     */
    private function mountWizard(): HasActions
    {
        $page = Livewire::test(AccessTokens::class)->mountAction(TestAction::make('createToken')->table())->instance();

        $this->assertInstanceOf(AccessTokens::class, $page);
        $this->assertIsString($page->mountedActions[0]['arguments'][RevealOnceSecret::RUN_ARGUMENT] ?? null);

        return $page;
    }
}
