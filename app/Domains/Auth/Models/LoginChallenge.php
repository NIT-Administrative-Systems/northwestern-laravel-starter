<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Actions\Local\AuthenticateWithLoginCode;
use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Actions\Local\RequestLoginCode;
use App\Domains\Auth\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Core\Models\BaseModel;
use Carbon\CarbonImmutable;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * Represents the OTP challenge state for a local user authentication attempt.
 *
 * @see RequestLoginCode
 * @see AuthenticateWithLoginCode
 * @see IssueLoginChallenge
 * @see VerifyLoginChallengeCode
 * @see SendLoginCodeEmailJob
 */
class LoginChallenge extends BaseModel
{
    use PrunesAfterRetentionPeriod;

    protected $casts = [
        'attempts' => 'int',
        'locked_until' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'email_sent_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];

    protected $hidden = ['code_hash'];

    /** @var list<string> */
    protected array $auditExclude = ['code_hash'];

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.login_challenges';
    }

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
