<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Actions;

use App\Domains\Access\Actions\AddPeopleToRole;
use App\Filament\Administration\Imports\AddPeopleToRoleImporter;
use Filament\Actions\ImportAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;

/**
 * Adds the people in an uploaded CSV to a role, through {@see AddPeopleToRoleImporter}. It's
 * offered where {@see AddPeopleAction} is, with the same role and permissions.
 */
class ImportPeopleAction extends ImportAction
{
    public static function getDefaultName(): ?string
    {
        return 'importPeople';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer(AddPeopleToRoleImporter::class)
            ->authorize(fn (mixed $livewire): bool|Response => AddPeopleAction::authorizeFor($livewire))
            ->label('Upload a CSV File')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading('Add People from a CSV File')
            ->chunkSize(AddPeopleToRole::CHUNK_SIZE)
            ->maxRows(AddPeopleToRole::MAX_PEOPLE)
            ->options(fn (mixed $livewire): array => array_filter(['role_id' => AddPeopleAction::roleOf($livewire)?->getKey()]));
    }
}
