<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use App\Domains\Core\Enums\ExternalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Northwestern\SysDev\Chassis\Http\Responses\ProblemDetails;
use Symfony\Component\HttpFoundation\Response;

class ServiceDownError extends Exception
{
    /** @param  positive-int|null  $retryAttempted */
    public function __construct(
        protected ExternalService $service,
        protected ?string $additionalMessage = null,
        protected ?int $retryAttempted = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $baseMessage = Str::limit($this->additionalMessage ?? '') ?: 'error';

        $message = sprintf(
            'External service %s is unavailable: %s',
            $this->service->value,
            $baseMessage
        );

        if ($this->retryAttempted !== null) {
            $timesText = $this->retryAttempted === 1 ? 'time' : 'times';
            $message = sprintf(
                'External service %s is unavailable (retried %d %s): %s',
                $this->service->value,
                $this->retryAttempted,
                $timesText,
                $baseMessage
            );
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * The outage is in another service, so show the 503 page rather than a 500 with the stack trace.
     * API and JSON requests get Problem Details. The error is still reported.
     */
    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ProblemDetails::serviceUnavailable(
                detail: sprintf('%s is temporarily unavailable.', $this->service->label()),
                retryAfter: 60,
            );
        }

        return response()->view('errors.503', [], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
