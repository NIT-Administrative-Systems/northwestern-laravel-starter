<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * Scope to role activity audit records (role_assigned / role_removed events for Users).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function roleActivity(Builder $query): Builder
    {
        return $query
            ->whereIn('event', ['role_assigned', 'role_removed'])
            ->where('auditable_type', new User()->getMorphClass());
    }

    /**
     * Derive the specific role(s) that changed by diffing old_values and new_values.
     *
     * Each entry includes id, name, and role_type as captured at the time of the change.
     *
     * @return list<array{id: int, name: string, role_type: string}>
     */
    public function getChangedRoles(): array
    {
        return once(function () {
            /** @var list<array{id: int, name: string, role_type: string}> $oldRoles */
            $oldRoles = $this->old_values['roles'] ?? [];
            /** @var list<array{id: int, name: string, role_type: string}> $newRoles */
            $newRoles = $this->new_values['roles'] ?? [];

            $oldIds = array_column($oldRoles, 'id');
            $newIds = array_column($newRoles, 'id');

            return match ($this->event) {
                'role_assigned' => array_values(array_filter($newRoles, fn (array $r): bool => ! in_array($r['id'], $oldIds))),
                'role_removed' => array_values(array_filter($oldRoles, fn (array $r): bool => ! in_array($r['id'], $newIds))),
                default => [],
            };
        });
    }
}
