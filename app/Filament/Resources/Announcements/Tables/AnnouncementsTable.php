<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Tables;

use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Announcement $record): string => $record->audienceSummary()),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not published'),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('No end'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(AnnouncementStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => self::whereStatus($query, AnnouncementStatus::tryFrom((string) ($data['value'] ?? '')))),
                SelectFilter::make('severity')
                    ->options(AnnouncementSeverity::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No announcements')
            ->emptyStateDescription('Write an announcement to show people a message in a banner across the application.');
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private static function whereStatus(Builder $query, ?AnnouncementStatus $status): Builder
    {
        $now = Carbon::now();

        return match ($status) {
            null => $query,
            AnnouncementStatus::Draft => $query->whereNull('published_at'),
            AnnouncementStatus::Scheduled => $query->whereNotNull('published_at')->where('starts_at', '>', $now),
            AnnouncementStatus::Live => $query->live($now),
            AnnouncementStatus::Ended => $query->whereNotNull('published_at')->where('ends_at', '<=', $now),
        };
    }
}
