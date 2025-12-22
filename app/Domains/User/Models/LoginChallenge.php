<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Actions\Local\IssueLoginChallenge;
use App\Domains\User\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\User\Jobs\SendLoginCodeEmailJob;
use App\Http\Controllers\Auth\Local\SendLoginCodeController;
use App\Http\Controllers\Auth\Local\VerifyLoginCodeController;
use Carbon\CarbonImmutable;

/**
 * Represents the OTP challenge state for a local user authentication attempt.
 *
 * @see SendLoginCodeController
 * @see VerifyLoginCodeController
 * @see IssueLoginChallenge
 * @see VerifyLoginChallengeCode
 * @see SendLoginCodeEmailJob
 */
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
        $now ??= CarbonImmutable::now();

        return $this->expires_at->lessThan($now);
    }

    public function isLocked(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->locked_until !== null && $this->locked_until->greaterThan($now);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isActive(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return ! $this->isConsumed() && ! $this->isExpired($now) && ! $this->isLocked($now);
    }
}
