<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models\Concerns;

use App\Domains\Core\Models\Concerns\Auditable;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(Auditable::class)]
class AuditableTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::flush();
        request()->replace([]);

        parent::tearDown();
    }

    public function test_transform_audit_adds_trace_id_impersonator_and_livewire_component(): void
    {
        Context::add(ApiRequestContext::TRACE_ID, 'trace-123');
        $this->bindImpersonateService(42);

        request()->replace([
            'components' => [
                ['snapshot' => json_encode(['memo' => ['name' => 'users.table']])],
            ],
        ]);

        $user = User::factory()->make();
        $result = $user->transformAudit([
            'url' => Livewire::getUpdateUri(),
        ]);

        $this->assertSame('trace-123', $result['trace_id']);
        $this->assertSame(42, $result['impersonator_user_id']);
        $this->assertSame(Livewire::getUpdateUri() . '#users.table', $result['url']);
    }

    public function test_transform_audit_handles_invalid_livewire_snapshot_without_error(): void
    {
        $this->bindImpersonateService(null);

        request()->replace([
            'components' => [
                ['snapshot' => '{invalid-json'],
            ],
        ]);

        $user = User::factory()->make();
        $result = $user->transformAudit([
            'url' => Livewire::getUpdateUri(),
        ]);

        $this->assertArrayNotHasKey('trace_id', $result);
        $this->assertNull($result['impersonator_user_id']);
        $this->assertSame(Livewire::getUpdateUri(), $result['url']);
    }

    public function test_transform_audit_does_not_modify_url_when_not_livewire_request(): void
    {
        $this->bindImpersonateService(7);

        request()->replace([]);

        $user = User::factory()->make();
        $result = $user->transformAudit([
            'url' => '/api/v1/users',
        ]);

        $this->assertSame(7, $result['impersonator_user_id']);
        $this->assertSame('/api/v1/users', $result['url']);
    }

    public function test_transform_audit_does_not_append_component_when_livewire_url_but_no_snapshot(): void
    {
        $this->bindImpersonateService(null);

        request()->replace([
            'components' => [
                ['other_key' => 'value'],
            ],
        ]);

        $user = User::factory()->make();
        $result = $user->transformAudit([
            'url' => Livewire::getUpdateUri(),
        ]);

        $this->assertSame(Livewire::getUpdateUri(), $result['url']);
    }

    private function bindImpersonateService(?int $impersonatorId): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('getImpersonatorId')->andReturn($impersonatorId);
        $impersonate->shouldReceive('isImpersonating')->andReturn($impersonatorId !== null);

        $this->app->instance('impersonate', $impersonate);
    }
}
