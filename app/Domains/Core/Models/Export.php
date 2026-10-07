<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Exports\Models\Export as BaseExport;
use Illuminate\Database\Eloquent\Builder;

/**
 * A Filament table export, and its file.
 *
 * Exports are kept for `platform.retention.exports` days after they finish (7 by default), then
 * the scheduled `model:prune` deletes each one and its file. Filament prunes them one by one, so
 * the file goes too, which is why this doesn't use Chassis's PrunesAfterRetentionPeriod: it
 * deletes in bulk.
 */
class Export extends BaseExport
{
    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('platform.retention.exports');

        if ($days === null || $days === '') {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('completed_at', '<', now()->subDays((int) $days));
    }

    protected function pruning(): void
    {
        $this->deleteFileDirectory();
    }
}
