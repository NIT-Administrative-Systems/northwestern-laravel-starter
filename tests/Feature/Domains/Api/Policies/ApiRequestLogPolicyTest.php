<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\Api\Policies\ApiRequestLogPolicy;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiRequestLogPolicy::class)]
final class ApiRequestLogPolicyTest extends TestCase
{
    public function test_reading_requests_needs_view_api_request_logs(): void
    {
        $log = ApiRequestLog::factory()->create();
        $reader = User::factory()->create();
        $reader->givePermissionTo(SystemPermission::ViewApiRequestLogs);
        $someone = User::factory()->create();

        $this->assertTrue($reader->can('viewAny', ApiRequestLog::class));
        $this->assertTrue($reader->can('view', $log));
        $this->assertFalse($someone->can('viewAny', ApiRequestLog::class));
        $this->assertFalse($someone->can('view', $log));
    }
}
