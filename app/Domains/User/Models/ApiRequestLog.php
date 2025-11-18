<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use Database\Factories\Domains\User\Models\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory;

    protected $table = 'user_api_token_request_logs';

    public const null UPDATED_AT = null;

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'failure_reason' => ApiRequestFailureEnum::class,
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function api_token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'user_api_token_id');
    }
}
