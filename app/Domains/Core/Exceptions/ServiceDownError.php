<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use App\Domains\Core\Enums\ExternalServiceEnum;
use Exception;
use Illuminate\Support\Str;

class ServiceDownError extends Exception
{
    public function __construct(
        protected ExternalServiceEnum $service,
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
}
