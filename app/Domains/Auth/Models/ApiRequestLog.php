<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Core\Models\Concerns\PrunesAfterRetentionPeriod;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Auth\Models\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Northwestern\SysDev\Chassis\Enums\ApiPrincipalType;
use Northwestern\SysDev\Chassis\Enums\ApiRequestFailure;
use Northwestern\SysDev\Chassis\Enums\OAuthGrantType;

class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory, PrunesAfterRetentionPeriod;

    public const null UPDATED_AT = null;

    protected $casts = [
        'failure_reason' => ApiRequestFailure::class,
        'principal_type' => ApiPrincipalType::class,
        'grant_type' => OAuthGrantType::class,
        'created_at' => 'datetime',
    ];

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.api_request_logs';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function oauth_client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }
}
