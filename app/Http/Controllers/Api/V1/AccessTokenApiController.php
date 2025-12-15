<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Actions\Api\IssueAccessToken;
use App\Domains\User\Models\AccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccessTokenRequest;
use App\Http\Resources\Api\V1\AccessTokenResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

class AccessTokenApiController extends Controller
{
    /**
     * List all access tokens for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->access_tokens()
            ->orderByRelevance()
            ->paginate(20);

        return AccessTokenResource::collection($tokens);
    }

    /**
     * Create a new access token for the authenticated user.
     */
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

    /**
     * Get details of a specific access token.
     */
    public function show(Request $request, AccessToken $token): AccessTokenResource
    {
        abort_if($token->user_id !== $request->user()->id, Response::HTTP_FORBIDDEN, 'This token does not belong to you.');

        return new AccessTokenResource($token);
    }

    /**
     * Revoke an access token.
     */
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
