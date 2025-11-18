<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAudit extends ViewRecord
{
    protected static string $resource = AuditResource::class;

    public function getRecordTitle(): string|Htmlable
    {
        return 'Audit #' . $this->record->getKey();
    }
}
