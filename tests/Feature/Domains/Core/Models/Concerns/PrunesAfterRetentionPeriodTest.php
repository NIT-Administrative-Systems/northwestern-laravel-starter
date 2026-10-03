<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models\Concerns;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Core\Models\Concerns\PrunesAfterRetentionPeriod;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\ImpersonationLog;
use App\Domains\User\Models\UserLoginRecord;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversTrait(PrunesAfterRetentionPeriod::class)]
final class PrunesAfterRetentionPeriodTest extends TestCase
{
    public function test_records_older_than_the_retention_period_are_pruned(): void
    {
        config(['platform.retention.login_records' => 365]);

        $old = UserLoginRecord::factory()->create(['logged_in_at' => now()->subDays(400)]);
        $recent = UserLoginRecord::factory()->create(['logged_in_at' => now()->subDays(10)]);

        new UserLoginRecord()->pruneAll();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_a_numeric_string_from_the_environment_is_accepted(): void
    {
        config(['platform.retention.login_records' => '365']);

        $old = UserLoginRecord::factory()->create(['logged_in_at' => now()->subDays(400)]);

        new UserLoginRecord()->pruneAll();

        $this->assertModelMissing($old);
    }

    public function test_a_null_or_empty_setting_keeps_every_record(): void
    {
        $old = UserLoginRecord::factory()->create(['logged_in_at' => now()->subYears(10)]);

        foreach ([null, ''] as $setting) {
            config(['platform.retention.login_records' => $setting]);

            new UserLoginRecord()->pruneAll();

            $this->assertModelExists($old);
        }
    }

    public function test_an_invalid_setting_is_rejected(): void
    {
        config(['platform.retention.login_records' => -1]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('[platform.retention.login_records]');

        new UserLoginRecord()->prunable();
    }

    /**
     * @param  class-string<Audit|UserLoginRecord|ImpersonationLog|LoginChallenge|ApiRequestLog>  $model
     */
    #[DataProvider('models')]
    public function test_each_model_reads_its_own_setting(string $model, string $key, string $column): void
    {
        config([$key => null]);
        $this->assertStringContainsString('1 = 0', (string) new $model()->prunable()->toSql());

        config([$key => 30]);
        $this->assertStringContainsString("\"{$column}\" <", (string) new $model()->prunable()->toSql());
    }

    /**
     * @return \Iterator<string, array{class-string<(ApiRequestLog | LoginChallenge | Audit | ImpersonationLog | UserLoginRecord)>, string, string}>
     */
    public static function models(): \Iterator
    {
        yield 'audits' => [Audit::class, 'platform.retention.audits', 'created_at'];
        yield 'login records' => [UserLoginRecord::class, 'platform.retention.login_records', 'logged_in_at'];
        yield 'impersonation logs' => [ImpersonationLog::class, 'platform.retention.impersonation_logs', 'created_at'];
        yield 'login challenges' => [LoginChallenge::class, 'platform.retention.login_challenges', 'created_at'];
        yield 'API request logs' => [ApiRequestLog::class, 'platform.retention.api_request_logs', 'created_at'];
    }
}
