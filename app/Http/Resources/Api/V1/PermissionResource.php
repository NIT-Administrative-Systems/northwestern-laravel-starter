<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\User\Enums\PermissionScopeEnum;
use App\Domains\User\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Permission
 */
#[OA\Schema(
    schema: 'Permission',
    required: ['id', 'name', 'label', 'scope', 'system_managed', 'api_relevant'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 17),
        new OA\Property(
            property: 'name',
            description: 'Machine-friendly permission name.',
            type: 'string',
            example: 'manage-users'
        ),
        new OA\Property(
            property: 'label',
            description: 'Human readable permission label.',
            type: 'string',
            example: 'Manage Users'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            example: 'Allows creating, editing, and disabling user accounts.',
            nullable: true
        ),
        new OA\Property(
            property: 'scope',
            description: 'Whether this permission applies system-wide or only to the authenticated user\'s context.',
            type: 'string',
            enum: [PermissionScopeEnum::SYSTEM_WIDE->value, PermissionScopeEnum::PERSONAL->value],
            example: PermissionScopeEnum::SYSTEM_WIDE->value
        ),
        new OA\Property(property: 'system_managed', type: 'boolean', example: false),
        new OA\Property(
            property: 'api_relevant',
            description: 'Whether this permission impacts API access patterns.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2024-02-10T16:22:00Z',
            nullable: true
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2024-04-02T19:43:12Z',
            nullable: true
        ),
    ],
    type: 'object'
)]
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'scope' => $this->scope->value,
            'system_managed' => $this->system_managed,
            'api_relevant' => $this->api_relevant,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
