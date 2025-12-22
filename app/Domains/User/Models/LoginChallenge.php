<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use Carbon\CarbonImmutable;

class LoginChallenge extends BaseModel
{
    protected $casts = [
        'attempts' => 'int',
        'locked_until' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'email_sent_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];

    protected $hidden = ['code_hash'];

    protected array $auditExclude = ['code_hash'];

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        $now ??= new CarbonImmutable();

        return $this->expires_at->lessThan($now);
    }

    public function isLocked(?CarbonImmutable $now = null): bool
    {
        $now ??= new CarbonImmutable();

        return $this->locked_until !== null && $this->locked_until->greaterThan($now);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isActive(?CarbonImmutable $now = null): bool
    {
        $now ??= new CarbonImmutable();

        return ! $this->isConsumed() && ! $this->isExpired($now) && ! $this->isLocked($now);
    }
}
