<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    /**
     * Get the authenticated user's information.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
