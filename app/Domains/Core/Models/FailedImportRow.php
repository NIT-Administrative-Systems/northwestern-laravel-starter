<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Imports\Models\FailedImportRow as BaseFailedImportRow;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * A row a Filament import couldn't process, kept so the person who imported it can download
 * them. The scheduled `model:prune` deletes them after `platform.retention.failed_import_rows`
 * days, in place of the month Filament's own model would keep them.
 */
class FailedImportRow extends BaseFailedImportRow
{
    use PrunesAfterRetentionPeriod;

    protected $table = 'failed_import_rows';

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.failed_import_rows';
    }
}
