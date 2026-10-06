<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'The API endpoints the Northwestern Laravel Starter ships. Applications add their own beside them.',
    title: 'Northwestern Laravel Starter API',
)]
abstract class ApiController extends BaseApiController
{
}
