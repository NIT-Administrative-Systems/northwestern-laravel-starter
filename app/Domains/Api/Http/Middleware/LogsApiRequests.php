<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Middleware;

use App\Domains\Api\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Northwestern\SysDev\Chassis\Http\Middleware\LogsPassportRequests;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Get the JSON-RPC method, tool and outcome of an MCP request.
     *
     * The HTTP status doesn't show how a tool call ended, because a failed call
     * still answers 200. The tool's arguments and results are never recorded.
     *
     * @return array<string, mixed>
     */
    protected function additionalLogData(Request $request, Response $response): array
    {
        $data = [...parent::additionalLogData($request, $response), 'mcp_method' => null, 'mcp_tool' => null, 'mcp_outcome' => null];

        if (! $request->routeIs('mcp.server')) {
            return $data;
        }

        $method = $request->json('method');
        $tool = $request->json('params.name');

        return [
            ...$data,
            'mcp_method' => is_string($method) ? Str::limit($method, 255, '') : null,
            'mcp_tool' => $method === 'tools/call' && is_string($tool) ? Str::limit($tool, 255, '') : null,
            'mcp_outcome' => $this->mcpOutcome($response),
        ];
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
            'mcp_method' => $data['mcp_method'],
            'mcp_tool' => $data['mcp_tool'],
            'mcp_outcome' => $data['mcp_outcome'],
        ]);
    }

    /**
     * Get how an MCP request ended: `ok`, `error` or `tool_error`.
     *
     * `error` is a JSON-RPC error and `tool_error` a tool that reported failure.
     * Null means the response isn't JSON-RPC, like a 401 or a streamed reply.
     */
    private function mcpOutcome(Response $response): ?string
    {
        $body = $response instanceof StreamedResponse ? null : json_decode((string) $response->getContent(), true);

        if (! is_array($body) || ! array_key_exists('jsonrpc', $body)) {
            return null;
        }

        if (array_key_exists('error', $body)) {
            return 'error';
        }

        return is_array($body['result'] ?? null) && ($body['result']['isError'] ?? false) === true ? 'tool_error' : 'ok';
    }
}
