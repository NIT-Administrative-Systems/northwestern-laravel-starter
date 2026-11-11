<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Imports\Models\Import as BaseImport;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * A Filament table import. Filament records imports with its own model; this one is here so the
 * scheduled `model:prune` deletes them after `platform.retention.imports` days. Their failed rows
 * go with them, and the uploaded file is Livewire's temporary upload, which Livewire cleans up.
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
