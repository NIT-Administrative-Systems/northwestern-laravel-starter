<?php

declare(strict_types=1);

namespace App\Domains\User\Http\Resources\Api\V1;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Http\Resources\Api\V1\RoleResource;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin User
 */
#[OA\Schema(
    schema: 'User',
    required: ['id', 'username', 'auth_type', 'first_name', 'last_name', 'full_name', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1024),
        new OA\Property(property: 'username', type: 'string', example: 'abc123'),
        new OA\Property(
            property: 'auth_type',
            description: 'How the user authenticates to the application.',
            type: 'string',
            enum: [AuthTypeEnum::SSO->value, AuthTypeEnum::LOCAL->value, AuthTypeEnum::API->value],
            example: AuthTypeEnum::API->value
        ),
        new OA\Property(property: 'first_name', type: 'string', example: 'NUIT'),
        new OA\Property(property: 'last_name', type: 'string', example: 'API'),
        new OA\Property(property: 'full_name', type: 'string', example: 'NUIT API'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nuit.api@example.edu'),
        new OA\Property(
            property: 'primary_affiliation',
            description: 'Primary Northwestern affiliation for the user.',
            type: 'string',
            enum: [AffiliationEnum::STUDENT->value, AffiliationEnum::FACULTY->value, AffiliationEnum::STAFF->value, AffiliationEnum::AFFILIATE->value, AffiliationEnum::OTHER->value],
            example: AffiliationEnum::OTHER->value,
            nullable: true
        ),
        new OA\Property(
            property: 'departments',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['IT Services', 'Information Security']
        ),
        new OA\Property(
            property: 'job_titles',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['Systems Administrator']
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2023-11-06T15:20:00Z',
            nullable: true
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2024-04-09T18:45:10Z',
            nullable: true
        ),
        new OA\Property(
            property: 'roles',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Role'),
            nullable: true
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UserResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ],
    type: 'object',
    example: [
        'data' => [
            'id' => 1,
            'username' => 'waw1234',
            'auth_type' => 'api',
            'first_name' => 'NUIT',
            'last_name' => 'API',
            'full_name' => 'NUIT API',
            'email' => null,
            'primary_affiliation' => 'not-matched',
            'departments' => [],
            'job_titles' => [],
            'created_at' => '2025-11-06T15:20:00+00:00',
            'updated_at' => '2025-04-09T18:45:10+00:00',
            'roles' => [
                [
                    'id' => 1,
                    'name' => 'API Role',
                    'role_type' => [
                        'slug' => 'api-integration',
                        'label' => 'API Integration',
                    ],
                    'created_at' => '2025-11-06T15:20:00+00:00',
                    'updated_at' => '2025-11-06T15:20:00+00:00',
                    'permissions' => [
                        [
                            'id' => 1,
                            'name' => 'view-users',
                            'label' => 'View Users',
                            'description' => 'Allows viewing all user profiles and their details.',
                            'scope' => 'system-wide',
                            'system_managed' => false,
                            'api_relevant' => true,
                            'created_at' => '2025-11-06T15:20:00+00:00',
                            'updated_at' => '2025-11-06T15:20:00+00:00',
                        ],
                    ],
                ],
            ],
        ],
    ]
)]
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
