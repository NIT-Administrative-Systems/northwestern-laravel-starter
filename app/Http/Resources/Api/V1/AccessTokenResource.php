<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\User\Models\AccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessToken
 */
class AccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'usage_count' => $this->usage_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'allowed_ips' => $this->allowed_ips,
        ];
    }
}
