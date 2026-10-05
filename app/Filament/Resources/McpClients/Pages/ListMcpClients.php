<?php

declare(strict_types=1);

namespace App\Filament\Resources\McpClients\Pages;

use App\Filament\Resources\McpClients\McpClientResource;
use Filament\Resources\Pages\ListRecords;

class ListMcpClients extends ListRecords
{
    protected static string $resource = McpClientResource::class;

    protected ?string $subheading = 'AI clients that registered themselves to connect to the MCP server';

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
