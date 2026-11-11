<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Imports\Models\FailedImportRow as BaseFailedImportRow;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * A row a Filament import couldn't process, kept
 * for the person who imported it to download.
 *
 * The scheduled `model:prune` deletes these after
 * `platform.retention.failed_import_rows` days.
 *
 * Filament's own model would keep them for a month.
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
