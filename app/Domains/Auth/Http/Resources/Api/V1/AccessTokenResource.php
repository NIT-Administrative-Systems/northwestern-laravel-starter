<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Resources\Api\V1;

use App\Domains\Auth\Models\AccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin AccessToken
 */
#[OA\Schema(
    schema: 'AccessToken',
    required: ['id', 'name', 'status', 'usage_count', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 23145),
        new OA\Property(property: 'name', type: 'string', example: 'CI Deployments'),
        new OA\Property(
            property: 'status',
            description: 'Operational status of the token, derived from revocation and expiration timestamps.',
            type: 'string',
            enum: ['active', 'expired', 'revoked'],
            example: 'active'
        ),
        new OA\Property(
            property: 'last_used_at',
            description: 'When this token was last used (null if never used).',
            type: 'string',
            format: 'date-time',
            example: '2024-04-11T14:35:05Z',
            nullable: true
        ),
        new OA\Property(property: 'usage_count', type: 'integer', example: 12),
        new OA\Property(
            property: 'expires_at',
            description: 'When this token expires. Null means the token does not expire.',
            type: 'string',
            format: 'date-time',
            example: '2024-12-31T06:00:00Z',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2024-03-01T18:11:00Z'
        ),
        new OA\Property(
            property: 'allowed_ips',
            description: 'Optional list of IPs or CIDR ranges allowed to use this token.',
            type: 'array',
            items: new OA\Items(type: 'string', example: '203.0.113.0/24'),
            example: ['10.0.0.0/24', '203.0.113.12'],
            nullable: true
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AccessTokenResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/AccessToken'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AccessTokenCreateInput',
    required: ['name'],
    properties: [
        new OA\Property(
            property: 'name',
            description: 'Friendly name for the token so you can identify it later.',
            type: 'string',
            example: 'CI deploy token'
        ),
        new OA\Property(
            property: 'expires_at',
            description: 'UTC timestamp (seconds) for when the token should expire. Must be at least 24 hours in the future.',
            type: 'integer',
            format: 'int64',
            example: 1751328000,
            nullable: true
        ),
        new OA\Property(
            property: 'allowed_ips',
            description: 'Optional allowlist of IPs or CIDR ranges that can use this token.',
            type: 'array',
            items: new OA\Items(type: 'string', example: '198.51.100.0/25'),
            nullable: true
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AccessTokenCreationMeta',
    required: ['bearer_token', 'message'],
    properties: [
        new OA\Property(
            property: 'bearer_token',
            description: 'The full bearer token. This is only returned once and cannot be retrieved again.',
            type: 'string',
            example: 'YiRqNBToe7EIXAnmtCB3a9gAwHNh0xXZUX3A9zARG5CWKUcDhRn4U9qGcG3q7TwB'
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Token generated successfully. Store the bearer token immediately; it will not be shown again.'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AccessTokenCreationResponse',
    required: ['data', 'meta'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/AccessToken'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/AccessTokenCreationMeta'),
    ],
    type: 'object',
    example: [
        'data' => [
            'id' => 23145,
            'name' => 'CI Deployments',
            'status' => 'active',
            'last_used_at' => null,
            'usage_count' => 0,
            'expires_at' => null,
            'created_at' => '2025-03-01T18:11:00Z',
            'allowed_ips' => ['10.0.0.0/24', '203.0.113.12'],
        ],
        'meta' => [
            'bearer_token' => 'YiRqNBToe7EIXAnmtCB3a9gAwHNh0xXZUX3A9zARG5CWKUcDhRn4U9qGcG3q7TwB',
            'message' => 'Token generated successfully. Store the bearer token immediately; it will not be shown again.',
        ],
    ]
)]
#[OA\Schema(
    schema: 'AccessTokenPage',
    required: ['data', 'links', 'meta'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/AccessToken')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
    ],
    type: 'object',
    example: [
        'data' => [
            [
                'id' => 1,
                'name' => 'Production Deploy',
                'status' => 'active',
                'last_used_at' => '2024-04-11T14:35:05Z',
                'usage_count' => 42,
                'expires_at' => null,
                'created_at' => '2024-03-01T18:11:00Z',
                'allowed_ips' => null,
            ],
        ],
        'links' => [
            'first' => 'https://example.edu/api/v1/me/tokens?page=1',
            'last' => 'https://example.edu/api/v1/me/tokens?page=1',
            'prev' => null,
            'next' => null,
        ],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'links' => [
                [
                    'url' => null,
                    'label' => '&laquo; Previous',
                    'page' => null,
                    'active' => false,
                ],
                [
                    'url' => 'https://example.edu/api/v1/me/tokens?page=1',
                    'label' => '1',
                    'page' => 1,
                    'active' => true,
                ],
                [
                    'url' => null,
                    'label' => 'Next &raquo;',
                    'page' => null,
                    'active' => false,
                ],
            ],
            'path' => 'https://example.edu/api/v1/me/tokens',
            'per_page' => 20,
            'to' => 15,
            'total' => 15,
        ],
    ]
)]
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
