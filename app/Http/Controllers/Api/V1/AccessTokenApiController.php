<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Actions\Api\IssueAccessToken;
use App\Domains\User\Models\AccessToken;
use App\Http\Requests\Api\V1\StoreAccessTokenRequest;
use App\Http\Resources\Api\V1\AccessTokenResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Access Tokens',
    description: 'Manage API access tokens for the authenticated user.'
)]
class AccessTokenApiController extends ApiController
{
    #[OA\Get(
        path: '/api/v1/me/tokens',
        operationId: 'list-access-tokens',
        summary: 'List all access tokens',
        security: [['bearerToken' => []]],
        tags: ['Access Tokens'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number of the results to fetch.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', format: 'int64', minimum: 1, example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated list of access tokens for the authenticated user.',
                content: new OA\JsonContent(ref: '#/components/schemas/AccessTokenPage')
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->access_tokens()
            ->orderByRelevance()
            ->paginate(20);

        return AccessTokenResource::collection($tokens);
    }

    #[OA\Post(
        path: '/api/v1/me/tokens',
        operationId: 'create-access-token',
        summary: 'Create an access token',
        security: [['bearerToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AccessTokenCreateInput')
        ),
        tags: ['Access Tokens'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Access token created. The plain bearer token is only returned once.',
                content: new OA\JsonContent(ref: '#/components/schemas/AccessTokenCreationResponse')
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation failed for the supplied token attributes.',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationProblemDetails')
            ),
        ]
    )]
    public function store(StoreAccessTokenRequest $request, IssueAccessToken $issueAccessToken): JsonResponse
    {
        $expiresAt = $request->filled('expires_at')
            ? Carbon::createFromTimestamp($request->input('expires_at'), 'UTC')
            : null;

        [$plainToken, $accessToken] = $issueAccessToken(
            user: $request->user(),
            name: trim((string) $request->input('name')),
            expiresAt: $expiresAt,
            allowedIps: $request->input('allowed_ips'),
        );

        return response()->json([
            'data' => new AccessTokenResource($accessToken),
            'meta' => [
                'bearer_token' => $plainToken,
                'message' => 'Token generated successfully. Store the bearer token immediately; it will not be shown again.',
            ],
        ], Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/v1/me/tokens/{token}',
        operationId: 'get-access-token',
        summary: 'Get an access token',
        security: [['bearerToken' => []]],
        tags: ['Access Tokens'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                description: 'The ID of the access token.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64', example: 42)
            ),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Access token details.',
                content: new OA\JsonContent(ref: '#/components/schemas/AccessTokenResponse')
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_FORBIDDEN,
                description: 'The token does not belong to the authenticated user.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Access token not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
        ]
    )]
    public function show(Request $request, AccessToken $token): AccessTokenResource
    {
        abort_if($token->user_id !== $request->user()->id, Response::HTTP_FORBIDDEN, 'This token does not belong to you.');

        return new AccessTokenResource($token);
    }

    #[OA\Delete(
        path: '/api/v1/me/tokens/{token}',
        operationId: 'revoke-access-token',
        summary: 'Revoke an access token',
        security: [['bearerToken' => []]],
        tags: ['Access Tokens'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                description: 'The ID of the access token to revoke.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64', example: 42)
            ),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Access token revoked.'),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_FORBIDDEN,
                description: 'The token does not belong to the authenticated user.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Cannot revoke the token currently being used.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Access token not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
        ]
    )]
    public function destroy(Request $request, AccessToken $token): Response
    {
        abort_if($token->user_id !== $request->user()->id, Response::HTTP_FORBIDDEN, 'This token does not belong to you.');

        if (Context::get(ApiRequestContext::TOKEN_ID) === $token->id) {
            throw ValidationException::withMessages([
                'token' => ['You cannot revoke the token you are currently using.'],
            ]);
        }

        $token->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
