<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Auth\Models\OAuthClient;
use App\Providers\FilamentServiceProvider;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FilamentServiceProvider::class)]
final class FilamentServiceProviderTest extends TestCase
{
    public function test_built_in_actions_title_case_the_model_label_in_their_names(): void
    {
        $label = 'sign-in record for an application';

        $create = CreateAction::make()->model(OAuthClient::class)->modelLabel($label);
        $this->assertSame('New Sign-In Record for an Application', $create->getLabel());
        $this->assertSame('Create Sign-In Record for an Application', $create->getModalHeading());

        $this->assertSame('Attach Sign-In Record for an Application', AttachAction::make()->model(OAuthClient::class)->modelLabel($label)->getModalHeading());

        $plural = 'sign-in records for applications';

        $this->assertSame('Delete Selected Sign-In Records for Applications', DeleteBulkAction::make()->model(OAuthClient::class)->pluralModelLabel($plural)->getModalHeading());

        $export = ExportAction::make()->model(OAuthClient::class)->pluralModelLabel($plural);
        $this->assertSame('Export Sign-In Records for Applications', $export->getLabel());
        $this->assertSame('Export Sign-In Records for Applications', $export->getModalHeading());
    }

    public function test_an_actions_own_label_still_wins(): void
    {
        $this->assertSame('Register Application', CreateAction::make()->model(OAuthClient::class)->label('Register Application')->getLabel());
    }

    public function test_a_tables_default_empty_state_title_cases_the_model_label(): void
    {
        $table = Table::make($this->createStub(\Filament\Tables\Contracts\HasTable::class))->pluralModelLabel('sign-in records');

        $this->assertSame('No Sign-In Records', $table->getEmptyStateHeading());
    }
}
