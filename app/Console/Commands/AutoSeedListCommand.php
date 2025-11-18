<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Database\ValueObjects\SeederInfo;
use App\Domains\Core\Services\IdempotentSeederResolver;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

class AutoSeedListCommand extends Command
{
    protected $signature = 'db:seed:list
                            {--show-dependencies : Show full dependency tree}
                            {--mermaid : Output as Mermaid diagram}
                            {--json : Output as JSON}';

    protected $description = 'List all discoverable seeders with their dependencies';

    public function handle(IdempotentSeederResolver $resolver): int
    {
        try {
            $seeders = $resolver->discover();

            if (blank($seeders)) {
                $this->components->warn('No seeders found with the #[AutoSeed] attribute.');

                return self::SUCCESS;
            }

            if ($this->option('mermaid')) {
                $this->outputMermaid($seeders);
            } elseif ($this->option('json')) {
                $this->outputJson($seeders);
            } else {
                $this->outputTable($seeders);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->components->error('Failed to discover seeders');
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Output seeders as a table
     *
     * @param  array<SeederInfo>  $seeders
     */
    private function outputTable(array $seeders): void
    {
        $rows = [];

        foreach ($seeders as $index => $seeder) {
            $order = $index + 1;
            $dependencies = $seeder->hasDependencies()
                ? $this->formatDependencies($seeder->getDependencyShortNames())
                : '<fg=gray>none</>';

            $rows[] = [
                "<fg=blue;options=bold>{$order}</>",
                "<fg=green>{$seeder->getShortName()}</>",
                $dependencies,
            ];
        }

        table(
            headers: ['#', 'Seeder', 'Dependencies'],
            rows: $rows
        );

        if ($this->option('show-dependencies')) {
            $this->newLine();
            $this->showDependencyTree($seeders);
        } else {
            $this->newLine();
            note('Use the <fg=cyan;options=bold>--show-dependencies</> option to view the full dependency tree.');
        }
    }

    /**
     * Format dependencies with proper truncation and styling
     *
     * @param  array<string>  $dependencies
     */
    private function formatDependencies(array $dependencies): string
    {
        if (count($dependencies) === 0) {
            return '<fg=gray>none</>';
        }

        if (count($dependencies) === 1) {
            return "<fg=yellow>{$dependencies[0]}</>";
        }

        // Show first 2 and indicate if there are more
        $shown = array_slice($dependencies, 0, 2);
        $formatted = array_map(fn ($dep) => "<fg=yellow>{$dep}</>", $shown);

        if (count($dependencies) > 2) {
            $remaining = count($dependencies) - 2;
            $formatted[] = "<fg=gray>+{$remaining} more</>";
        }

        return implode(', ', $formatted);
    }

    /**
     * Show a dependency tree
     *
     * @param  array<SeederInfo>  $seeders
     */
    private function showDependencyTree(array $seeders): void
    {
        $this->components->info('Dependency Tree:');
        $this->newLine();

        foreach ($seeders as $seeder) {
            $icon = $seeder->hasDependencies() ? '📦' : '📄';
            $this->line("  {$icon} <fg=cyan;options=bold>{$seeder->getShortName()}</>");

            if ($seeder->hasDependencies()) {
                $dependencies = $seeder->getDependencyShortNames();

                foreach ($dependencies as $index => $dep) {
                    $isLast = $index === count($dependencies) - 1;
                    $prefix = $isLast ? '      └──' : '      ├──';
                    $this->line("{$prefix} <fg=yellow>{$dep}</>");
                }
            } else {
                $this->line('      <fg=gray>└── (no dependencies)</>');
            }

            $this->newLine();
        }
    }

    /**
     * Output seeders as JSON
     *
     * @param  array<SeederInfo>  $seeders
     */
    private function outputJson(array $seeders): void
    {
        $data = [
            'total' => count($seeders),
            'seeders' => [],
        ];
        $order = 1;

        foreach ($seeders as $seeder) {
            $data['seeders'][] = [
                'order' => $order++,
                'class' => $seeder->className,
                'short_name' => $seeder->getShortName(),
                'depends_on' => $seeder->dependsOn,
            ];
        }

        $this->line(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * Output a Mermaid diagram for online visualization
     *
     * @param  array<SeederInfo>  $seeders
     */
    private function outputMermaid(array $seeders): void
    {
        $this->line('<fg=gray>```mermaid</>');
        $this->line('<fg=white>graph TD</>');

        $this->generateMermaidNodes($seeders);

        $this->line('<fg=gray>```</>');
        $this->newLine();

        $this->components->info('📋 Next Steps:');
        $this->components->bulletList([
            'Copy the Mermaid code above',
            'Visit <fg=cyan>https://mermaid.live</>',
            'Paste the code to visualize your seeder dependencies',
            'Export as SVG or PNG for documentation',
        ]);
    }

    /**
     * Generate Mermaid node definitions
     *
     * @param  array<SeederInfo>  $seeders
     */
    private function generateMermaidNodes(array $seeders): void
    {
        $processedNodes = [];

        // Define all nodes with labels
        foreach ($seeders as $seeder) {
            $nodeName = $this->toMermaidNodeName($seeder->getShortName());

            if (! in_array($nodeName, $processedNodes, true)) {
                $this->line(sprintf(
                    '    <fg=blue>%s</>["%s"]',
                    $nodeName,
                    $seeder->getShortName()
                ));
                $processedNodes[] = $nodeName;
            }
        }

        // Define relationships
        foreach ($seeders as $seeder) {
            if ($seeder->hasDependencies()) {
                $nodeName = $this->toMermaidNodeName($seeder->getShortName());

                foreach ($seeder->dependsOn as $dependency) {
                    $depNodeName = $this->toMermaidNodeName(class_basename($dependency));

                    $this->line(sprintf(
                        '    <fg=yellow>%s</> <fg=gray>--></> <fg=green>%s</>',
                        $depNodeName,
                        $nodeName
                    ));
                }
            }
        }
    }

    /**
     * Convert seeder name to Mermaid-safe node name
     */
    private function toMermaidNodeName(string $name): string
    {
        return str_replace(['Seeder', ' ', '-'], '', $name);
    }
}
