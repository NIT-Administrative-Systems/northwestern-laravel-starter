<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for all HTTP controllers.
 *
 * This abstract class provides common controller functionality through Laravel traits:
 * - AuthorizesRequests: Enables policy-based authorization via $this->authorize()
 * - ValidatesRequests: Enables request validation via $this->validate()
 *
 * All application controllers should extend this base class to inherit
 * these capabilities and maintain consistency across the application.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
