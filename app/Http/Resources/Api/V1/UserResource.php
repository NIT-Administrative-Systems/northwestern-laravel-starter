<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'username' => $this->username,
            'auth_type' => $this->auth_type->value,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'primary_affiliation' => $this->primary_affiliation?->value,
            'departments' => $this->departments,
            'job_titles' => $this->job_titles,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
