<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Users',
    description: 'Retrieve information about the authenticated API user.'
)]
class UserApiController extends ApiController
{
    #[OA\Get(
        path: '/api/v1/me',
        operationId: 'get-user-details',
        summary: 'Get authenticated user details',
        security: [['bearerToken' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Authenticated user profile.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
            ),
        ]
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
