<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use Filament\Actions\Exports\Models\Export as BaseExport;
use Illuminate\Database\Eloquent\Builder;

/**
 * A Filament table export belonging to a user.
 *
 * Extended to add pruning logic for automatic cleanup of old exports and their S3 files.
 */
class Export extends BaseExport
{
    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('completed_at', '<', now()->subDays(7));
    }

    protected function pruning(): void
    {
        $this->deleteFileDirectory();
    }
}
