<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Core API endpoints provided by the Northwestern Laravel Starter, including user profile information and access token management.',
    title: 'Northwestern Laravel Starter API',
)]
abstract class ApiController extends BaseApiController
{
}
