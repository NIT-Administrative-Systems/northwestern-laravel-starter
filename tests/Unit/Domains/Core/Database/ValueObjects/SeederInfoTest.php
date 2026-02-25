<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Database\ValueObjects;

use App\Domains\Auth\Seeders\PermissionSeeder;
use App\Domains\Auth\Seeders\RoleSeeder;
use App\Domains\Core\Database\ValueObjects\SeederInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeederInfo::class)]
class SeederInfoTest extends TestCase
{
    public function test_get_short_name_extracts_class_basename(): void
    {
        $info = new SeederInfo(className: RoleSeeder::class);

        $this->assertSame('RoleSeeder', $info->getShortName());
    }

    public function test_has_dependencies_returns_false_when_empty(): void
    {
        $info = new SeederInfo(className: PermissionSeeder::class);

        $this->assertFalse($info->hasDependencies());
    }

    public function test_has_dependencies_returns_true_when_present(): void
    {
        $info = new SeederInfo(
            className: RoleSeeder::class,
            dependsOn: [PermissionSeeder::class],
        );

        $this->assertTrue($info->hasDependencies());
    }

    public function test_get_dependency_short_names(): void
    {
        $info = new SeederInfo(
            className: RoleSeeder::class,
            dependsOn: [PermissionSeeder::class, RoleSeeder::class],
        );

        $this->assertSame(['PermissionSeeder', 'RoleSeeder'], $info->getDependencyShortNames());
    }

    public function test_get_dependency_short_names_returns_empty_when_no_dependencies(): void
    {
        $info = new SeederInfo(className: PermissionSeeder::class);

        $this->assertSame([], $info->getDependencyShortNames());
    }

    public function test_defaults_to_empty_depends_on(): void
    {
        $info = new SeederInfo(className: PermissionSeeder::class);

        $this->assertSame([], $info->dependsOn);
    }
}
