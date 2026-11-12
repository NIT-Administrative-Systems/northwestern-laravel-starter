<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\UserLoginRecords\Tables;

use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Administration\Exports\UserLoginRecordExporter;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserLoginRecordsTable
{
    public static function configure(Table $table, bool $isRelationManager = false): Table
    {
        return $table
            // A deactivated person's sign-ins still show who they were.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user' => fn (Relation $user) => $user->withoutGlobalScope(SoftDeletingScope::class)]))
            ->columns([
                TextColumn::make('user.clerical_name')
                    ->label('Name')
                    ->wrap()
                    ->searchable(query:
                        /** @phpstan-ignore-next-line  */
                        fn (Builder $query, string $search): Builder => $query->orWhereHas('user', fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)->searchByName($search)))
                    ->hidden($isRelationManager),
                TextColumn::make('user.username')
                    ->label('Username')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereHas('user', fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)->where('username', 'ilike', "%{$search}%")))
                    ->hidden($isRelationManager),
                TextColumn::make('logged_in_at')
                    ->label('Signed In')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('segment')
                    ->badge()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('ip_address')
                    ->placeholder('—')
                    ->label('IP Address')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->placeholder('—')
                    ->label('User Agent')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('logged_in_at', direction: 'desc')
            ->deferLoading()
            ->heading('Sign-In Records')
            ->searchable(! $isRelationManager)
            ->splitSearchTerms(false)
            ->searchDebounce('750ms')
            ->filters([
                SelectFilter::make('segment')
                    ->multiple()
                    ->options(UserSegment::class)
                    ->hidden($isRelationManager),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filters'),
            )
            ->recordActions([
                ViewAction::make()
                    ->label('View User')
                    ->url(fn (UserLoginRecord $record) => route('filament.administration.resources.users.view', ['record' => $record->user_id]))
                    ->hidden($isRelationManager),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->exporter(UserLoginRecordExporter::class)
                    ->hidden($isRelationManager),
            ])
            ->emptyStateHeading('No Sign-Ins')
            ->emptyStateDescription('Sign-ins appear here as people sign in.')
            ->emptyStateIcon('heroicon-o-arrow-right-on-rectangle');
    }
}
