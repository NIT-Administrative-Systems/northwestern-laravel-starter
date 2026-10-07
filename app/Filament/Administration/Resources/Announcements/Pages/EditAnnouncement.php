<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Announcements\Pages;

use App\Domains\Support\Actions\Announcements\DuplicateAnnouncement;
use App\Domains\Support\Actions\Announcements\EndAnnouncement;
use App\Domains\Support\Actions\Announcements\PublishAnnouncement;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Edits an announcement and moves it through its life: publish a draft, end a live one, show a
 * live one again to the people who dismissed it, or start over from a copy.
 *
 * @property-read Announcement $record
 */
class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AnnouncementResource::normalizeAudience($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedMegaphone)
                ->visible(fn (): bool => $this->record->status === AnnouncementStatus::Draft)
                ->modalHeading('Publish Announcement')
                ->modalDescription('Your changes are saved first. People in its audience see it from when it starts.')
                ->modalSubmitActionLabel('Publish')
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label('Starts')
                        ->seconds(false)
                        ->default(fn (): Carbon => Carbon::now())
                        ->required(),
                    DateTimePicker::make('ends_at')
                        ->label('Ends')
                        ->seconds(false)
                        ->after('starts_at')
                        ->helperText('Optional. Leave blank to keep it showing until you end it.'),
                    Toggle::make('notify')
                        ->label('Also notify the audience')
                        ->helperText('Once, when it starts: in the notifications bell, and by email to people who haven\'t turned announcement emails off.'),
                ])
                ->action(function (array $data, PublishAnnouncement $publish): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $publish(
                        $this->record,
                        Carbon::parse($data['starts_at']),
                        filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
                        (bool) ($data['notify'] ?? false),
                    );

                    Notification::make()->title('Announcement Published')->success()->send();
                    $this->redirect(AnnouncementResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('end')
                ->label('End Now')
                ->icon(Heroicon::OutlinedStopCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === AnnouncementStatus::Live)
                ->requiresConfirmation()
                ->modalHeading('End Announcement')
                ->modalDescription('It leaves the banner now and stays on the Announcements page as a past announcement. It can\'t be published again, but you can duplicate it.')
                ->modalSubmitActionLabel('End Now')
                ->action(function (EndAnnouncement $end): void {
                    $end($this->record);

                    Notification::make()->title('Announcement Ended')->success()->send();
                    $this->redirect(AnnouncementResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('showAgain')
                ->label('Show Again')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === AnnouncementStatus::Live && $this->record->dismissals()->exists())
                ->requiresConfirmation()
                ->modalHeading('Show Again to People Who Dismissed It')
                ->modalDescription(fn (): string => 'It returns to the banner for the ' . $this->record->dismissals()->count() . ' people who dismissed it, for example after an important change.')
                ->modalSubmitActionLabel('Show Again')
                ->action(function (): void {
                    $this->record->dismissals()->delete();

                    Notification::make()->title('Showing Again')->body('Everyone in its audience sees it again, including people who dismissed it.')->success()->send();
                }),
            Action::make('duplicate')
                ->label('Duplicate')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->action(function (DuplicateAnnouncement $duplicate): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $copy = $duplicate($this->record, $user);

                    Notification::make()->title('Copied to a New Draft')->success()->send();
                    $this->redirect(AnnouncementResource::getUrl('edit', ['record' => $copy]));
                }),
            DeleteAction::make(),
        ];
    }
}
