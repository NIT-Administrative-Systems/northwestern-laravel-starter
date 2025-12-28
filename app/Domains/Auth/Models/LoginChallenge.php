<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\Auth\Http\Controllers\Local\SendLoginCodeController;
use App\Domains\Auth\Http\Controllers\Local\VerifyLoginCodeController;
use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Core\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use InvalidArgumentException;

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
    use MassPrunable;

    protected $casts = [
        'attempts' => 'int',
        'locked_until' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'email_sent_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];

    protected $hidden = ['code_hash'];

    protected array $auditExclude = ['code_hash'];

    /**
     * Automatically deletes records older than the configured retention period.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $retentionDays = config('auth.local.code.retention_days');

        if ($retentionDays === null) {
            return static::query()->whereRaw('1 = 0');
        }

        if (! is_numeric($retentionDays) || $retentionDays < 0) {
            throw new InvalidArgumentException(
                'Login challenge retention days must be a positive integer or null.'
            );
        }

        return static::query()->where('created_at', '<', now()->subDays((int) $retentionDays));
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
