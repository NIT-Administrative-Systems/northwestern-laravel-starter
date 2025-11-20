<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Attributes;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(AutoSeed::class)]
class AutoSeedTest extends TestCase
{
    public function test_it_can_be_instantiated_without_dependencies(): void
    {
        $attribute = new AutoSeed();

        $this->assertSame([], $attribute->dependsOn);
    }

    public function test_it_can_be_instantiated_with_dependencies(): void
    {
        $dependencies = [FakeSeederA::class, FakeSeederB::class];
        $attribute = new AutoSeed($dependencies);

        $this->assertSame($dependencies, $attribute->dependsOn);
    }

    public function test_it_can_be_discovered_via_reflection(): void
    {
        $reflection = new ReflectionClass(SeederWithAutoSeed::class);
        $attributes = $reflection->getAttributes(AutoSeed::class);
        $this->assertCount(1, $attributes);

        /** @var AutoSeed $attributeInstance */
        $attributeInstance = $attributes[0]->newInstance();

        $this->assertInstanceOf(AutoSeed::class, $attributeInstance);
        $this->assertSame([FakeSeederA::class], $attributeInstance->dependsOn);
    }
}

class FakeSeederA implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}
class FakeSeederB implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}

#[AutoSeed(dependsOn: [FakeSeederA::class])]
class SeederWithAutoSeed implements IdempotentSeederInterface
{
    public function run(): void
    {
        //
    }
}
