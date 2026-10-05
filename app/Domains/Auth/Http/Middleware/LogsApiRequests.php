<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\ApiRequestLog;
use Northwestern\SysDev\Chassis\Http\Middleware\LogsPassportRequests;

class LogsApiRequests extends LogsPassportRequests
{
    protected function isEnabled(): bool
    {
        return (bool) config('api.request_logging.enabled');
    }

    protected function isSamplingEnabled(): bool
    {
        return (bool) config('api.request_logging.sampling.enabled');
    }

    protected function sampleRate(): float
    {
        return (float) config('api.request_logging.sampling.rate', 1.0);
    }

    protected function persistLog(array $data): void
    {
        ApiRequestLog::create([
            'trace_id' => $data['trace_id'],
            'user_id' => $data['user_id'],
            'principal_type' => $data['principal_type'],
            'oauth_client_id' => $data['oauth_client_id'],
            'token_id' => $data['oauth_token_id'],
            'grant_type' => $data['oauth_grant_type'],
            'method' => $data['method'],
            'path' => $data['path'],
            'route_name' => $data['route_name'],
            'ip_address' => $data['ip_address'],
            'status_code' => $data['status_code'],
            'duration_ms' => $data['duration_ms'],
            'request_bytes' => $data['request_bytes'],
            'response_bytes' => $data['response_bytes'],
            'user_agent' => $data['user_agent'],
            'failure_reason' => $data['failure_reason'],
        ]);
    }
}
