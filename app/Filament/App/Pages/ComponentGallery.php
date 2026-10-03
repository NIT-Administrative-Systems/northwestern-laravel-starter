<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Starter placeholder: Filament's common components in the Northwestern theme, with
 * sample content, so you can see what you are building with. Linked from the
 * dashboard, never in the navigation, and not available in production.
 *
 * Delete this page when you no longer need it.
 *
 * @property-read Schema $form
 */
class ComponentGallery extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Component gallery';

    protected static ?string $slug = 'gallery';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ! app()->isProduction();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Text input')->placeholder('Willie the Wildcat')->required(),
                    Select::make('school')->label('Select')->options([
                        'mccormick' => 'McCormick School of Engineering',
                        'medill' => 'Medill School of Journalism',
                        'weinberg' => 'Weinberg College of Arts and Sciences',
                    ]),
                    DatePicker::make('date')->label('Date picker'),
                    Radio::make('term')->label('Radio')->options(['fall' => 'Fall', 'winter' => 'Winter', 'spring' => 'Spring'])->inline(),
                    Toggle::make('notifications')->label('Toggle'),
                    Checkbox::make('agree')->label('Checkbox'),
                ]),
                Textarea::make('notes')->label('Textarea')->rows(3),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [
                1 => ['name' => 'Sample record one', 'status' => 'Approved', 'active' => true, 'updated' => '2026-09-29'],
                2 => ['name' => 'Sample record two', 'status' => 'Pending', 'active' => true, 'updated' => '2026-09-15'],
                3 => ['name' => 'Sample record three', 'status' => 'Rejected', 'active' => false, 'updated' => '2026-09-01'],
            ])
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Pending' => 'warning',
                        default => 'danger',
                    }),
                IconColumn::make('active')->label('Active')->boolean(),
                TextColumn::make('updated')->label('Updated')->date(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('Starter placeholder')
                    ->description('Sample content showing Filament\'s components in the Northwestern theme. It is not available in production. Delete app/Filament/App/Pages/ComponentGallery.php when you no longer need it.')
                    ->icon(Heroicon::OutlinedSwatch)
                    ->warning(),

                Section::make('Buttons')
                    ->schema([
                        Actions::make([
                            Action::make('primary')->label('Primary'),
                            Action::make('gray')->label('Gray')->color('gray'),
                            Action::make('outlined')->label('Outlined')->outlined(),
                            Action::make('withIcon')->label('With icon')->icon(Heroicon::OutlinedSparkles),
                            Action::make('danger')->label('Danger')->color('danger'),
                            Action::make('link')->label('Link')->link(),
                            Action::make('small')->label('Small')->size('sm'),
                        ]),
                    ]),

                Section::make('Badges')
                    ->schema([
                        Flex::make([
                            Text::make('Primary')->badge()->color('primary')->grow(false),
                            Text::make('Success')->badge()->color('success')->grow(false),
                            Text::make('Warning')->badge()->color('warning')->grow(false),
                            Text::make('Danger')->badge()->color('danger')->grow(false),
                            Text::make('Info')->badge()->color('info')->grow(false),
                            Text::make('Gray')->badge()->color('gray')->grow(false),
                        ]),
                    ]),

                Section::make('Callouts')
                    ->schema([
                        Callout::make('Information')->description('Something people should know.')->info(),
                        Callout::make('Success')->description('Something worked.')->success(),
                        Callout::make('Warning')->description('Something needs attention.')->warning(),
                        Callout::make('Danger')->description('Something went wrong.')->danger(),
                    ]),

                Section::make('Form fields')
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->livewireSubmitHandler('submitExample')
                            ->footer([
                                Actions::make([
                                    Action::make('submitExample')->label('Submit')->submit('submitExample'),
                                ]),
                            ]),
                    ]),

                Section::make('Table')
                    ->schema([EmbeddedTable::make()]),

                Section::make('Notifications and modals')
                    ->schema([
                        Actions::make([
                            Action::make('notifySuccess')
                                ->label('Success notification')
                                ->color('gray')
                                ->action(fn () => Notification::make()->title('Saved')->body('A sample success notification.')->success()->send()),
                            Action::make('notifyDanger')
                                ->label('Danger notification')
                                ->color('gray')
                                ->action(fn () => Notification::make()->title('Something went wrong')->body('A sample danger notification.')->danger()->send()),
                            Action::make('confirm')
                                ->label('Confirmation modal')
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalDescription('A sample confirmation. Nothing happens when you confirm.')
                                ->action(fn () => null),
                            Action::make('slideOver')
                                ->label('Slide-over')
                                ->color('gray')
                                ->slideOver()
                                ->modalHeading('A sample slide-over')
                                ->modalDescription('Slide-overs suit longer forms and details.')
                                ->modalSubmitAction(false),
                        ]),
                    ]),
            ]);
    }

    public function submitExample(): void
    {
        $this->form->getState();

        Notification::make()->title('Form submitted')->body('The sample form validated. Nothing was saved.')->success()->send();
    }
}
