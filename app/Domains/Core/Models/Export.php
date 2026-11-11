<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Filament\Actions\Exports\Models\Export as BaseExport;
use Illuminate\Database\Eloquent\Builder;

/**
 * A Filament table export, and its file.
 *
 * Exports are kept for `platform.retention.exports` days after they finish, seven
 * by default, and then the scheduled `model:prune` deletes each with its file.
 *
 * Chassis's PrunesAfterRetentionPeriod deletes in bulk, which would
 * leave the files behind, so this prunes exports one at a time.
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
