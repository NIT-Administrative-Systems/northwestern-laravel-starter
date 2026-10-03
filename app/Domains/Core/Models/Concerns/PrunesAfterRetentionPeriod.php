<?php

declare(strict_types=1);

namespace App\Domains\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use InvalidArgumentException;

/**
 * Deletes records older than a configured number of days when the scheduled
 * `model:prune` command runs. A null or empty setting keeps records forever.
 *
 * Settings come from env() without an (int) cast, so `SOME_RETENTION_DAYS=null`
 * disables pruning instead of becoming 0 and deleting everything.
 */
trait PrunesAfterRetentionPeriod
{
    use MassPrunable;

    /** The config key holding the number of days to keep records. */
    abstract protected function retentionConfigKey(): string;

    /** The timestamp the retention period counts from. Prefer an indexed column. */
    protected function retentionColumn(): string
    {
        return 'created_at';
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $key = $this->retentionConfigKey();
        $retentionDays = config($key);

        if ($retentionDays === null || $retentionDays === '') {
            return static::query()->whereRaw('1 = 0');
        }

        if (! is_numeric($retentionDays) || (int) $retentionDays < 0) {
            throw new InvalidArgumentException("[{$key}] must be a number of days (0 or more) or null.");
        }

        return static::query()->where($this->retentionColumn(), '<', now()->subDays((int) $retentionDays));
    }
}
