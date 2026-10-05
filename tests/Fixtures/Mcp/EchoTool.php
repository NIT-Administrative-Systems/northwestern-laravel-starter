<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mcp;

use App\Domains\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * A tool for tests: echoes its text with the name of the person it acts as. The starter's
 * server ships with no tools.
 */
#[Description('Echoes text back with the name of the person calling.')]
class EchoTool extends Tool
{
    protected string $name = 'echo';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate(['text' => ['required', 'string', 'min:2']]);
        $user = $request->user();

        return Response::structured([
            'text' => $validated['text'],
            'person' => $user instanceof User ? $user->full_name : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['text' => $schema->string()->required()];
    }
}
