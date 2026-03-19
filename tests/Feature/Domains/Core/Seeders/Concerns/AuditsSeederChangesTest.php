<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Seeders\Concerns;

use App\Domains\Auth\Models\Role;
use App\Domains\Core\Seeders\Concerns\AuditsSeederChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsSeederChanges::class)]
class AuditsSeederChangesTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetObservedModels();

        parent::tearDown();
    }

    public function test_with_auditing_returns_callback_value(): void
    {
        $seeder = new TestSeeder();

        $this->assertSame('seeded', $seeder->callWithAuditing([Role::class], fn () => 'seeded'));
    }

    public function test_with_auditing_returns_null_from_void_callback(): void
    {
        $seeder = new TestSeeder();

        $this->assertNull($seeder->callWithAuditing([Role::class], function () {
        }));
    }

    public function test_with_auditing_throws_for_non_auditable_model(): void
    {
        $seeder = new TestSeeder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $seeder->callWithAuditing([NonAuditableModel::class], fn () => null);
    }

    public function test_with_auditing_validates_all_models_before_registering_any(): void
    {
        $seeder = new TestSeeder();

        $this->expectException(InvalidArgumentException::class);

        // Role is valid, NonAuditableModel is not. Should throw before any registration.
        $seeder->callWithAuditing([Role::class, NonAuditableModel::class], fn () => null);
    }

    public function test_with_auditing_skips_observer_registration_in_testing_environment(): void
    {
        $seeder = new TestSeeder();
        $seeder->callWithAuditing([Role::class], fn () => null);

        $this->assertEmpty($this->getObservedModels());
    }

    public function test_with_auditing_executes_callback_in_testing_environment(): void
    {
        $seeder = new TestSeeder();
        $executed = false;

        $seeder->callWithAuditing([Role::class], function () use (&$executed) {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    public function test_with_auditing_registers_observer_in_non_testing_environment(): void
    {
        $seeder = new TestSeeder();

        $this->withProductionEnvironment(function () use ($seeder) {
            $seeder->callWithAuditing([Role::class], fn () => null);
        });

        $observed = $this->getObservedModels();
        $this->assertArrayHasKey(Role::class, $observed);
    }

    public function test_register_observer_once_prevents_duplicate_registration(): void
    {
        $seeder = new TestSeeder();

        // First call registers the observer.
        $this->withProductionEnvironment(function () use ($seeder) {
            $seeder->callWithAuditing([Role::class], fn () => null);
        });

        $this->assertCount(1, $this->getObservedModels());

        // Second call should skip registration — count stays at 1.
        $this->withProductionEnvironment(function () use ($seeder) {
            $seeder->callWithAuditing([Role::class], fn () => null);
        });

        $this->assertCount(1, $this->getObservedModels());
    }

    public function test_with_auditing_accepts_empty_model_list(): void
    {
        $seeder = new TestSeeder();

        $this->assertSame('ok', $seeder->callWithAuditing([], fn () => 'ok'));
    }

    /**
     * Read the static $observedModels from the concrete TestSeeder class.
     *
     * @return array<class-string, true>
     */
    private function getObservedModels(): array
    {
        $property = new \ReflectionClass(TestSeeder::class)->getProperty('observedModels');

        return $property->getValue();
    }

    /**
     * Reset the static $observedModels on the TestSeeder class.
     */
    private function resetObservedModels(): void
    {
        $property = new \ReflectionClass(TestSeeder::class)->getProperty('observedModels');
        $property->setValue(null, []);
    }

    /**
     * Run a callback with the application environment set to production.
     */
    private function withProductionEnvironment(callable $callback): void
    {
        app()->detectEnvironment(fn () => 'production');

        try {
            $callback();
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }
}

class TestSeeder extends Seeder
{
    use AuditsSeederChanges;

    /**
     * @param  list<class-string>  $models
     */
    public function callWithAuditing(array $models, callable $callback): mixed
    {
        return $this->withAuditing($models, $callback);
    }
}

/**
 * A plain Eloquent model that does NOT implement the Auditable contract.
 */
class NonAuditableModel extends Model
{
}
