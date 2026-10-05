<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\HandleCors as BaseHandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's CORS handling, plus `cors.open_paths`: endpoints any origin may call, without
 * credentials, such as the MCP server, its discovery documents, client registration and the
 * OAuth token endpoint, which browser-based MCP clients call directly. Every other path
 * follows `config/cors.php` as usual.
 */
class HandleCors extends BaseHandleCors
{
    /**
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @return Response A preflight response is a plain Symfony response, whatever the parent's docblock says.
     */
    public function handle($request, Closure $next): Response
    {
        /** @var list<string> $openPaths */
        $openPaths = $this->container['config']->get('cors.open_paths', []);

        if ($openPaths === [] || ! $request->is(...$openPaths)) {
            return parent::handle($request, $next);
        }

        $this->cors->setOptions([
            ...$this->container['config']->get('cors', []),
            'allowed_origins' => ['*'],
            'allowed_origins_patterns' => [],
            'supports_credentials' => false,
        ]);

        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        return $this->cors->addActualRequestHeaders($next($request), $request);
    }
}
