<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Exports;

use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Exports\BaseExporter;
use App\Filament\Exports\RoleExporter;
use App\Filament\Exports\UserExporter;
use Filament\Actions\Exports\Models\Export;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exporters are under app/Filament, outside the coverage gate, so this keeps CSV injection out
 * of every export.
 */
#[CoversNothing]
final class BaseExporterTest extends TestCase
{
    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function cells(): array
    {
        return [
            'formula' => ['=HYPERLINK("https://evil.example","Click")', '\'=HYPERLINK("https://evil.example","Click")'],
            'plus' => ['+1+1', "'+1+1"],
            'minus' => ['-2+3', "'-2+3"],
            'at sign' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab' => ["\t=1", "'\t=1"],
            'carriage return' => ["\r=1", "'\r=1"],
            'plain text' => ['Willie Wildcat', 'Willie Wildcat'],
            'formula later in the text' => ['a=b', 'a=b'],
            'number' => [-5, -5],
            'null' => [null, null],
        ];
    }

    #[DataProvider('cells')]
    public function test_a_cell_a_spreadsheet_would_run_is_neutralized(mixed $cell, mixed $expected): void
    {
        $this->assertSame($expected, BaseExporter::neutralize($cell));
    }

    // These two exported names and descriptions as written before the base class.
    public function test_user_and_role_exports_neutralize_every_text_cell(): void
    {
        $user = User::factory()->create(['first_name' => '=HYPERLINK("https://evil.example")', 'last_name' => 'Wildcat']);
        $role = Role::query()->firstOrFail();
        $role->forceFill(['name' => '+cmd|calc'])->saveQuietly();

        $userRow = (new UserExporter(new Export(), ['first_name' => 'First Name', 'last_name' => 'Last Name'], []))($user);
        $roleRow = (new RoleExporter(new Export(), ['name' => 'Name'], []))($role);

        $this->assertSame(['\'=HYPERLINK("https://evil.example")', 'Wildcat'], $userRow);
        $this->assertSame(["'+cmd|calc"], $roleRow);
    }

    public function test_the_completion_message_names_the_rows_and_any_failures(): void
    {
        $export = new Export();
        $export->forceFill(['successful_rows' => 1, 'total_rows' => 1]);
        $this->assertSame('Exported 1 user.', UserExporter::getCompletedNotificationBody($export));

        $export->forceFill(['successful_rows' => 1200, 'total_rows' => 1203]);
        $this->assertSame('Exported 1,200 roles. 3 rows failed.', RoleExporter::getCompletedNotificationBody($export));
    }
}
