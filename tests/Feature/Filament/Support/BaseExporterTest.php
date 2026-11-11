<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support;

use App\Domains\Access\Models\Role;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\Core\Models\Audit;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Administration\Exports\ApiRequestLogExporter;
use App\Filament\Administration\Exports\AuditExporter;
use App\Filament\Administration\Exports\RoleExporter;
use App\Filament\Administration\Exports\SupportTicketExporter;
use App\Filament\Administration\Exports\UserExporter;
use App\Filament\Administration\Exports\UserLoginRecordExporter;
use App\Filament\Support\BaseExporter;
use Filament\Actions\Exports\Models\Export;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exporters are under app/Filament, outside
 * the coverage gate, so this test keeps
 * CSV injection out of every export.
 */
#[CoversNothing]
final class BaseExporterTest extends TestCase
{
    /**
     * @return \Iterator<string, array{mixed, mixed}>
     */
    public static function cells(): \Iterator
    {
        yield 'formula' => ['=HYPERLINK("https://evil.example","Click")', '\'=HYPERLINK("https://evil.example","Click")'];
        yield 'plus' => ['+1+1', "'+1+1"];
        yield 'minus' => ['-2+3', "'-2+3"];
        yield 'at sign' => ['@SUM(A1)', "'@SUM(A1)"];
        yield 'tab' => ["\t=1", "'\t=1"];
        yield 'carriage return' => ["\r=1", "'\r=1"];
        yield 'plain text' => ['Willie Wildcat', 'Willie Wildcat'];
        yield 'formula later in the text' => ['a=b', 'a=b'];
        yield 'number' => [-5, -5];
        yield 'null' => [null, null];
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

    // An export reads each row's person on its own, so the relation must find them.
    public function test_exports_name_people_who_have_since_been_deactivated(): void
    {
        $person = User::factory()->create(['username' => 'wil123', 'first_name' => 'Willie', 'last_name' => 'Wildcat']);
        $audit = Audit::query()->forceCreate([
            'event' => 'updated',
            'auditable_type' => $person->getMorphClass(),
            'auditable_id' => $person->getKey(),
            'user_type' => $person->getMorphClass(),
            'user_id' => $person->getKey(),
            'impersonator_user_id' => $person->getKey(),
        ]);
        $signIn = UserLoginRecord::factory()->for($person)->create();
        $request = ApiRequestLog::factory()->create(['user_id' => $person->getKey()]);
        $ticket = SupportTicket::factory()->for($person)->create();
        $person->delete();

        $this->assertSame(['wil123', 'Willie Wildcat', 'wil123'], (new AuditExporter(new Export(), ['user.username' => 'Username', 'user.full_name' => 'Name', 'impersonator.username' => 'Impersonator'], []))($audit->fresh()));
        $this->assertSame(['wil123', 'Willie Wildcat'], (new UserLoginRecordExporter(new Export(), ['user.username' => 'Username', 'user.full_name' => 'Name'], []))($signIn->fresh()));
        $this->assertSame(['wil123'], (new ApiRequestLogExporter(new Export(), ['user.username' => 'Username'], []))($request->fresh()));
        $this->assertSame(['Willie Wildcat', 'wil123'], (new SupportTicketExporter(new Export(), ['user.full_name' => 'Name', 'user.username' => 'Username'], []))($ticket->fresh()));
    }

    public function test_the_completion_message_names_the_rows_and_any_failures(): void
    {
        $export = new Export();
        $export->forceFill(['successful_rows' => 1, 'total_rows' => 1]);
        $this->assertSame('Exported one user.', UserExporter::getCompletedNotificationBody($export));

        $export->forceFill(['successful_rows' => 1200, 'total_rows' => 1203]);
        $this->assertSame('Exported 1,200 roles. Three rows failed.', RoleExporter::getCompletedNotificationBody($export));

        $export->forceFill(['successful_rows' => 12, 'total_rows' => 12]);
        $this->assertSame('Exported 12 sign-in records.', UserLoginRecordExporter::getCompletedNotificationBody($export));
    }
}
