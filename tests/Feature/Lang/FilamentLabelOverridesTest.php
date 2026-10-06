<?php

declare(strict_types=1);

namespace Tests\Feature\Lang;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * lang/vendor overrides a few of Filament's labels so they're title case (see "Interface copy" in
 * .github/copilot-instructions.md). A Filament upgrade that renames one of those keys would
 * silently bring back Filament's wording, so every overridden key must still exist in Filament.
 */
#[CoversNothing]
final class FilamentLabelOverridesTest extends TestCase
{
    public function test_every_overridden_label_still_exists_in_filament(): void
    {
        $namespaces = resolve('translator')->getLoader()->namespaces();
        $overrides = File::allFiles(lang_path('vendor'));

        $this->assertNotEmpty($overrides);

        foreach ($overrides as $file) {
            [$namespace, $locale, $group] = explode('/', $file->getRelativePathname(), 3);
            $group = str($group)->beforeLast('.php')->toString();

            $this->assertArrayHasKey($namespace, $namespaces, "{$namespace} isn't a registered translation namespace.");

            $original = require "{$namespaces[$namespace]}/{$locale}/{$group}.php";

            foreach (array_keys(Arr::dot(require $file->getPathname())) as $key) {
                $this->assertIsString(data_get($original, $key), "Filament no longer has {$namespace}::{$group}.{$key}.");
            }
        }
    }
}
