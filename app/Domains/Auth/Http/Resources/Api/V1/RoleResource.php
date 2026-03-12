<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Resources\Api\V1;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Role
 */
#[OA\Schema(
    schema: 'RoleType',
    required: ['slug', 'label'],
    properties: [
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: RoleTypeEnum::ApiIntegration->value,
            enum: [RoleTypeEnum::SystemManaged->value, RoleTypeEnum::ApplicationAdmin->value, RoleTypeEnum::ApplicationRole->value, RoleTypeEnum::ApiIntegration->value]
        ),
        new OA\Property(property: 'label', type: 'string', example: 'API Integration'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Role',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 4),
        new OA\Property(property: 'name', type: 'string', example: 'Content Manager'),
        new OA\Property(property: 'assignment_locked', type: 'boolean', example: false),
        new OA\Property(
            property: 'role_type',
            ref: '#/components/schemas/RoleType',
            description: 'Included when the role_type relationship is loaded.',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2025-01-15T18:00:00Z',
            nullable: true
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2025-04-21T12:05:23Z',
            nullable: true
        ),
        new OA\Property(
            property: 'permissions',
            description: 'Included when permissions are eager loaded.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Permission'),
            nullable: true
        ),
        new OA\Property(
            property: 'users_count',
            description: 'Included when a user count aggregate is loaded.',
            type: 'integer',
            example: 18,
            nullable: true
        ),
    ],
    type: 'object'
)]
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'assignment_locked' => $this->assignment_locked,
            'role_type' => $this->when(
                $this->relationLoaded('role_type'),
                fn () => [
                    'slug' => $this->role_type?->slug->value,
                    'label' => $this->role_type?->label,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'users_count' => $this->when(
                isset($this->users_count),
                fn () => $this->users_count
            ),
        ];
    }
}
