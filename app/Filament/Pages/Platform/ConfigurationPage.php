<?php

declare(strict_types=1);

namespace App\Filament\Pages\Platform;

use App\Domains\User\Enums\PermissionEnum;
use App\Filament\Navigation\AdministrationNavGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Platform Configuration Page
 *
 * Displays key platform settings in a simple two-column table.
 *
 * To add configuration settings, modify the getConfigurationData() method.
 */
class ConfigurationPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.platform.configuration-page';

    protected static ?string $title = 'Configuration';

    protected ?string $subheading = 'System configuration overview';

    protected static ?string $navigationLabel = 'Configuration';

    protected static ?string $slug = 'configuration';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::PLATFORM;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()->hasPermissionTo(PermissionEnum::MANAGE_ALL);
    }

    /**
     * The configuration data to display.
     *
     * @return array<string, string>
     */
    protected function getConfigurationData(): array
    {
        /**
         * @return string[]
         */
        $systemMock = static function (string $label, string $mockEnabledParam, string $baseUrlParam): array {
            if (config($mockEnabledParam) === true) {
                return [
                    "{$label} API Mocking Enabled" => 'Yes',
                    "{$label} API Base URL" => url('/api/mock'),
                ];
            }

            return [
                "{$label} API Mocking Enabled" => 'No',
                "{$label} API Base URL" => config($baseUrlParam),
            ];
        };

        return [
            'Platform URL' => url('/'),
            'Environment Lockdown' => config('platform.lockdown.enabled')
                ? 'Enabled: Non-default roles required for access'
                : 'Disabled: No role-based access restrictions',
            ...$systemMock('EventHub', 'nusoa.eventHub.mock', 'nusoa.eventHub.baseUrl'),
            'Mail Driver' => config('mail.default'),
            'Mail Server' => strtolower((string) config('mail.default')) === 'ses'
                ? 'AWS SES (Live)'
                : Str::of((string) config('mail.mailers.smtp.host'))->append(':', config('mail.mailers.smtp.port'))->toString(),
        ];
    }

    public function table(Table $table): Table
    {
        $configData = $this->getConfigurationData();
        $records = [];

        $index = 1;
        foreach ($configData as $parameter => $value) {
            $records[$index] = [
                'parameter' => $parameter,
                'value' => $value ?? 'N/A',
            ];
            $index++;
        }

        return $table
            ->records(fn (): array => $records)
            ->columns([
                TextColumn::make('parameter')
                    ->label('Parameter')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->placeholder('N/A')
                    ->wrap()
                    ->copyable(),
            ])
            ->searchable(false)
            ->paginated(false)
            ->poll(null);
    }
}
