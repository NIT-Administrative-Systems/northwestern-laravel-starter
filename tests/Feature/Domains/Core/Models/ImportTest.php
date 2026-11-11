<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models;

use App\Domains\Core\Models\FailedImportRow;
use App\Domains\Core\Models\Import;
use App\Domains\User\Models\User;
use App\Filament\Administration\Imports\AddPeopleToRoleImporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ImportTest extends TestCase
{
    public function test_imports_and_their_failed_rows_are_pruned_after_thirty_days_by_default(): void
    {
        $old = $this->importCreated(daysAgo: 31);
        $recent = $this->importCreated(daysAgo: 29);

        $this->artisan('model:prune', ['--model' => [Import::class]])->assertSuccessful();

        $this->assertNull(Import::query()->find($old->getKey()));
        $this->assertSame(0, FailedImportRow::query()->where('import_id', $old->getKey())->count());
        $this->assertNotNull(Import::query()->find($recent->getKey()));
        $this->assertSame(1, FailedImportRow::query()->where('import_id', $recent->getKey())->count());
    }

    public function test_failed_rows_have_their_own_retention_period(): void
    {
        $import = $this->importCreated(daysAgo: 3);

        config(['platform.retention.failed_import_rows' => 2]);
        $this->artisan('model:prune', ['--model' => [FailedImportRow::class]])->assertSuccessful();

        $this->assertNotNull(Import::query()->find($import->getKey()));
        $this->assertSame(0, FailedImportRow::query()->where('import_id', $import->getKey())->count());
    }

    public function test_null_keeps_imports_and_failed_rows(): void
    {
        $import = $this->importCreated(daysAgo: 400);

        config(['platform.retention.imports' => null, 'platform.retention.failed_import_rows' => null]);
        $this->artisan('model:prune', ['--model' => [Import::class, FailedImportRow::class]])->assertSuccessful();

        $this->assertNotNull(Import::query()->find($import->getKey()));
        $this->assertSame(1, FailedImportRow::query()->where('import_id', $import->getKey())->count());
    }

    private function importCreated(int $daysAgo): Import
    {
        $this->travel(-$daysAgo)->days();

        $import = new Import();
        $import->forceFill([
            'file_name' => 'people.csv',
            'file_path' => '',
            'importer' => AddPeopleToRoleImporter::class,
            'total_rows' => 2,
            'user_id' => User::factory()->create()->getKey(),
        ])->save();

        $failedRow = new FailedImportRow();
        $failedRow->forceFill(['import_id' => $import->getKey(), 'data' => ['person' => 'abc123']])->save();

        $this->travelBack();

        return $import;
    }
}
