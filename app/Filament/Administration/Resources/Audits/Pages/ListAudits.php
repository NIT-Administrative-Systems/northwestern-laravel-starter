<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Audits\Pages;

use App\Filament\Administration\Resources\Audits\AuditResource;
use Filament\Resources\Pages\ListRecords;

class ListAudits extends ListRecords
{
    protected static string $resource = AuditResource::class;

    protected ?string $subheading = 'Every change to an audited record.';
}
