<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Imports\Models\Import as BaseImport;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * A Filament table import.
 *
 * The scheduled `model:prune` deletes it after `platform.retention.imports` days.
 *
 * Its failed rows go with it. The uploaded
 * file is a Livewire temporary upload,
 * which Livewire cleans up itself.
 */
class Import extends BaseImport
{
    use PrunesAfterRetentionPeriod;

    protected $table = 'imports';

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.imports';
    }
}
