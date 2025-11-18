<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $trace_id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url The URL of the request.
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tags
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property int|null $impersonator_user_id
 */
class Audit extends BaseModel
{
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }
}
