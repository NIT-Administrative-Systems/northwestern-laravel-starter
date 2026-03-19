<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Tables\RoleDefinitionHistoryTable;
use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property-read Role $record
 */
class RoleDefinitionHistory extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.resources.roles.pages.role-definition-history';

    protected static ?string $navigationLabel = 'Definition History';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name . ' — Definition History';
    }

    public function mount(int|string $record): void
    {
        /** @var Role $resolved */
        $resolved = $this->resolveRecord($record);
        $this->record = $resolved;

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->record), 403);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return RoleDefinitionHistoryTable::configure(
            $table->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->where('auditable_type', Role::class)
                    ->where('auditable_id', $this->record->getKey())
            )
        );
    }
}
