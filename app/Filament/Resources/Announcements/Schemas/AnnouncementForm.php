<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use App\Domains\Auth\Models\Role;
use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Enums\Affiliation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 5])
                ->schema([
                    Group::make([
                        Section::make('Message')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(debounce: 500),
                                MarkdownEditor::make('body')
                                    ->label('Message')
                                    ->helperText('Markdown, including links. The banner shows the first two lines, with a link to the rest on the Announcements page.')
                                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                                    ->required()
                                    ->maxLength(5000)
                                    ->live(debounce: 500),
                            ]),
                        Section::make('Severity')
                            ->description('Sets the color and icon, and which announcement the banner shows first. People can dismiss anything but a critical announcement.')
                            ->schema([
                                ToggleButtons::make('severity')
                                    ->hiddenLabel()
                                    ->options(AnnouncementSeverity::class)
                                    ->default(AnnouncementSeverity::Info)
                                    ->inline()
                                    ->required()
                                    ->live(),
                            ]),
                        Section::make('Audience')
                            ->schema([
                                Radio::make('audience')
                                    ->hiddenLabel()
                                    ->options(AnnouncementAudience::class)
                                    ->default(AnnouncementAudience::Everyone)
                                    ->required()
                                    ->live(),
                                Select::make('role_ids')
                                    ->label('Roles')
                                    ->multiple()
                                    // A targeted announcement names at least one role or affiliation.
                                    ->required(fn (Get $get): bool => self::isTargeted($get) && blank($get('affiliations')))
                                    ->validationMessages(['required' => 'Choose at least one role or affiliation.'])
                                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->visible(fn (Get $get): bool => self::isTargeted($get)),
                                CheckboxList::make('affiliations')
                                    ->label('Affiliations')
                                    ->options(collect(Affiliation::cases())->mapWithKeys(fn (Affiliation $affiliation): array => [$affiliation->value => $affiliation->getLabel()])->all())
                                    ->columns(3)
                                    ->live()
                                    ->visible(fn (Get $get): bool => self::isTargeted($get)),
                            ]),
                        Section::make('Schedule')
                            ->description('Times are in your timezone.')
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Starts')
                                    ->seconds(false)
                                    ->required(),
                                DateTimePicker::make('ends_at')
                                    ->label('Ends')
                                    ->seconds(false)
                                    ->after('starts_at')
                                    ->helperText('Optional. Leave blank to keep it showing until you end it.'),
                            ])
                            ->columns(2)
                            ->visible(fn (?Announcement $record): bool => $record instanceof Announcement && $record->status !== AnnouncementStatus::Draft),
                    ])->columnSpan(['xl' => 3]),
                    Section::make('Preview')
                        ->description('How the banner looks. Links don\'t work here.')
                        ->schema([
                            View::make('filament.resources.announcements.preview')
                                ->viewData(fn (Get $get): array => ['announcement' => self::previewOf($get)]),
                        ])
                        ->columnSpan(['xl' => 2]),
                ]),
        ])->columns(1);
    }

    private static function isTargeted(Get $get): bool
    {
        $audience = $get('audience');

        return $audience === AnnouncementAudience::Targeted || $audience === AnnouncementAudience::Targeted->value;
    }

    private static function previewOf(Get $get): Announcement
    {
        $severity = $get('severity');

        return new Announcement([
            'title' => (string) ($get('title') ?: 'Your title'),
            'body' => (string) ($get('body') ?: 'Your message.'),
            'severity' => $severity instanceof AnnouncementSeverity ? $severity : (AnnouncementSeverity::tryFrom((string) $severity) ?? AnnouncementSeverity::Info),
        ]);
    }
}
