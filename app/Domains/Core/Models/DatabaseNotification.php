<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use App\Domains\User\Models\User;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * A notification in a panel's notification bell. {@see User::notifications()} uses this model so
 * the scheduled `model:prune` deletes them after `platform.retention.notifications` days, read
 * or not.
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use PrunesAfterRetentionPeriod;

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.notifications';
    }
}
