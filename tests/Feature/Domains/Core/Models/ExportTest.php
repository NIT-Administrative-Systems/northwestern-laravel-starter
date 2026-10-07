<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models;

use App\Domains\Core\Models\Export;
use App\Domains\User\Models\User;
use App\Filament\Exports\AuditExporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ExportTest extends TestCase
{
    public function test_exports_are_pruned_a_week_after_they_finish_by_default(): void
    {
        $old = $this->exportCompleted(daysAgo: 8);
        $this->exportCompleted(daysAgo: 6);
        $this->exportCompleted(daysAgo: null);

        $this->assertSame([$old->getKey()], new Export()->prunable()->pluck('id')->all());
    }

    public function test_the_retention_period_is_configurable_and_null_keeps_exports(): void
    {
        $this->exportCompleted(daysAgo: 8);
        $recent = $this->exportCompleted(daysAgo: 2);

        config(['platform.retention.exports' => 1]);
        $this->assertCount(2, new Export()->prunable()->get());

        config(['platform.retention.exports' => null]);
        $this->assertCount(0, new Export()->prunable()->get());
        $this->assertTrue($recent->exists);
    }

    private function exportCompleted(?int $daysAgo): Export
    {
        $export = new Export();
        $export->forceFill([
            'completed_at' => $daysAgo === null ? null : now()->subDays($daysAgo),
            'file_disk' => 'local',
            'exporter' => AuditExporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->create()->getKey(),
        ])->save();

        return $export;
    }
}
