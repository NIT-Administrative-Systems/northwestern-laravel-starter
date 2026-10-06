<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\SecurityScheme(
    securityScheme: 'oauth2',
    type: 'oauth2',
    description: 'OAuth 2.0. A service integration uses the client credentials flow with a service client created in Administration, and its token acts as the client\'s API user. An application registered in Administration uses the authorization code flow with PKCE, and its token acts as the person who connected it, within the scopes they approved.',
    flows: [
        new OA\Flow(
            tokenUrl: '/oauth/token',
            flow: 'clientCredentials',
            scopes: ['view-users' => 'View everyone\'s profiles and details.'],
        ),
        new OA\Flow(
            authorizationUrl: '/oauth/authorize',
            tokenUrl: '/oauth/token',
            refreshUrl: '/oauth/token',
            flow: 'authorizationCode',
            scopes: ['view-users' => 'View everyone\'s profiles and details.'],
        ),
    ],
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerToken',
    type: 'http',
    description: 'A personal access token created on the Account page, or an access token from either OAuth flow, sent as a bearer token in the Authorization header.',
    scheme: 'bearer'
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    required: ['first', 'last', 'prev', 'next'],
    properties: [
        new OA\Property(property: 'first', type: 'string', format: 'uri', example: 'https://example.test/api/v1/records?page=1'),
        new OA\Property(property: 'last', type: 'string', format: 'uri', example: 'https://example.test/api/v1/records?page=1'),
        new OA\Property(property: 'prev', type: 'string', format: 'uri', example: null, nullable: true),
        new OA\Property(property: 'next', type: 'string', format: 'uri', example: null, nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginationLink',
    required: ['label', 'page', 'active'],
    properties: [
        new OA\Property(
            property: 'url',
            type: 'string',
            format: 'uri',
            example: 'https://example.test/api/v1/records?page=1',
            nullable: true
        ),
        new OA\Property(property: 'label', type: 'string', example: '&laquo; Previous'),
        new OA\Property(property: 'page', type: 'integer', example: null, nullable: true),
        new OA\Property(property: 'active', type: 'boolean', example: false),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    required: ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total', 'links'],
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'path', type: 'string', format: 'uri', example: 'https://example.test/api/v1/records'),
        new OA\Property(property: 'per_page', type: 'integer', example: 20),
        new OA\Property(property: 'to', type: 'integer', example: 15, nullable: true),
        new OA\Property(property: 'total', type: 'integer', example: 15),
        new OA\Property(
            property: 'links',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/PaginationLink'),
            example: [
                [
                    'url' => null,
                    'label' => '&laquo; Previous',
                    'page' => null,
                    'active' => false,
                ],
                [
                    'url' => 'https://example.test/api/v1/records?page=1',
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
            ]
        ),
    ],
    type: 'object'
)]
abstract class BaseApiController extends Controller
{
}
