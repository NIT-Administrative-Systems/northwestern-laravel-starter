<?php

declare(strict_types=1);

namespace App\Console\Commands\NorthwesternLaravelStarter;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * After creating a new Laravel application with the starter kit, it's important to customize the project configuration
 * to fit your specifications. This command prompts the user with a series of questions and will update the project
 * files by replacing {@see ProjectReplacementToken} strings with the user's input.
 *
 * The command follows this workflow:
 * * 1. Prompt the user for input on various project settings (app name, database names, etc.)
 * * 2. Back up original files before modifying them
 * * 3. Show a Git diff preview of changes before applying them
 * * 4. Confirm with the user before proceeding
 * * 5. Apply changes and clean up temporary files
 */
class CustomizeProjectCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'project:customize
                            {--force : Force the operation without confirmation}';

    protected $description = 'Customize the project configuration after installation.';

    /**
     * Stores the original content of files that will be modified.
     * Keys are file paths, values are the original contents.
     * Used for comparison and backup purposes.
     *
     * @var array<string, string>
     */
    private array $originalFileContents = [];

    /**
     * Stores the token replacements to be made.
     * Keys are {@see ProjectReplacementToken} strings, and values are the user's input.
     *
     * @var array<string, string>
     */
    private array $replacements = [];

    /**
     * List of file paths that need to be modified based on the collected replacements.
     * These files will have tokens replaced with user-provided values.
     *
     * @var string[]
     */
    private array $filesToModify = [];

    /**
     * List of temporary file paths created during the process.
     * These include the ".new" files used for diff comparisons and will be cleaned up later.
     *
     * @var string[]
     */
    private array $tempFiles = [];

    public function handle(): int
    {

        $this->line("\n<fg=magenta>🛠️  Northwestern Laravel Starter - Project Customization</>");

        if (! $this->confirmToProceed()) {
            $this->components->warn('❌ Project customization cancelled.');

            return self::FAILURE;
        }

        if (Process::run(['git', 'diff', '--exit-code'])->failed()) {
            $this->components->warn(
                '⚠️ It is recommended to commit your local changes before proceeding in order to easily revert the file changes from this command.'
            );

            if (! confirm(
                label: 'Do you want to continue without committing your changes?',
                default: false,
            )) {
                $this->components->warn('❌ Project customization cancelled.');

                return self::FAILURE;
            }
        }

        $this->newLine();

        try {
            $this->collectReplacements();

            $this->collectFilesToModify();

            $this->backupAndPrepareChanges();

            if (! $this->showDiffAndConfirm()) {
                $this->cleanup();
                $this->components->warn('❌ No changes applied. Exiting.');

                return self::FAILURE;
            }

            $this->applyChanges();
            $this->cleanup();
        } catch (Exception $e) {
            $this->components->error('❌ An error occurred: ' . $e->getMessage());
            $this->cleanup();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->success(sprintf(
            'Project customization completed. You can safely delete the <fg=cyan>%s</> directory.',
            base_path('app/Console/Commands/NorthwesternLaravelStarter')
        ));

        return self::SUCCESS;
    }

    /**
     * Collect all user inputs for each {@see ProjectReplacementToken}.
     */
    private function collectReplacements(): void
    {
        foreach (ProjectReplacementToken::cases() as $token) {
            // A replacement for this token does not need to be collected since it's dynamically generated
            if ($token === ProjectReplacementToken::APPLICATION_SLUG) {
                continue;
            }

            $methodName = 'collect' . Str::studly($token->name) . 'Replacement';

            $this->replacements[$token->value] = method_exists($this, $methodName)
                ? $this->{$methodName}()
                : $this->collectGenericReplacement($token);

            // Derive application slug from application name if using Herd
            if (! isset($this->replacements[ProjectReplacementToken::APPLICATION_SLUG->value])) {
                $appName = $this->replacements[ProjectReplacementToken::APPLICATION_NAME->value] ?? '';
                $this->replacements[ProjectReplacementToken::APPLICATION_SLUG->value] = Str::slug($appName);
            }
        }
    }

    /**
     * Collect application name replacement.
     */
    private function collectApplicationNameReplacement(): string
    {
        return text(
            label: ProjectReplacementToken::APPLICATION_NAME->question(),
            placeholder: ProjectReplacementToken::APPLICATION_NAME->placeholder(),
            required: true,
            validate: fn ($value): ?string => blank($value) ? 'Application name is required' : null
        );
    }

    /**
     * Collect database name replacement.
     */
    private function collectDatabaseNameReplacement(): string
    {
        $appName = $this->replacements[ProjectReplacementToken::APPLICATION_NAME->value] ?? null;
        $suggestedDbName = $appName
            ? Str::snake($appName) . '_local'
            : null;

        return text(
            label: ProjectReplacementToken::DATABASE_NAME->question(),
            placeholder: $suggestedDbName ?? ProjectReplacementToken::DATABASE_NAME->placeholder(),
            default: $suggestedDbName,
            required: true,
            validate: function ($value): ?string {
                if (blank($value)) {
                    return 'Database name is required';
                }

                if (preg_match('/[^a-z0-9_]/', $value)) {
                    return 'Database name should only contain lowercase letters, numbers, and underscores';
                }

                return null;
            }
        );
    }

    /**
     * Collect test database name replacement.
     */
    private function collectTestDatabaseNameReplacement(): string
    {
        $dbName = $this->replacements[ProjectReplacementToken::DATABASE_NAME->value] ?? null;
        $suggestedTestDbName = $dbName
            ? Str::replace('_local', '', "{$dbName}_test")
            : null;

        return text(
            label: ProjectReplacementToken::TEST_DATABASE_NAME->question(),
            placeholder: $suggestedTestDbName ?? ProjectReplacementToken::TEST_DATABASE_NAME->placeholder(),
            default: $suggestedTestDbName,
            required: true,
            validate: function ($value): ?string {
                if (blank($value)) {
                    return 'Test database name is required';
                }

                if (preg_match('/[^a-z0-9_]/', $value)) {
                    return 'Test database name should only contain lowercase letters, numbers, and underscores';
                }

                return null;
            }
        );
    }

    /**
     * Collect test database name replacement.
     */
    private function collectS3BucketNameReplacement(): string
    {
        $appName = $this->replacements[ProjectReplacementToken::APPLICATION_NAME->value] ?? null;
        $suggestedBucketName = $appName
            ? Str::slug($appName)
            : null;

        return text(
            label: ProjectReplacementToken::S3_BUCKET_NAME->question(),
            placeholder: $suggestedBucketName ?? ProjectReplacementToken::S3_BUCKET_NAME->placeholder(),
            default: $suggestedBucketName,
            required: true,
            validate: function ($value): ?string {
                /** @link {https://docs.aws.amazon.com/AmazonS3/latest/userguide/bucketnamingrules.html} */
                if (blank($value)) {
                    return 'S3 bucket name is required';
                }

                if (strlen($value) < 3 || strlen($value) > 63) {
                    return 'S3 bucket name must be between 3 and 63 characters long';
                }

                if (in_array(preg_match('/^[a-z0-9]/', $value), [0, false], true) || in_array(preg_match('/[a-z0-9]$/', $value), [0, false], true)) {
                    return 'S3 bucket name must start and end with a letter or number';
                }

                if (in_array(preg_match('/^[a-z0-9.-]+$/', $value), [0, false], true)) {
                    return 'S3 bucket name should only contain lowercase letters, numbers, hyphens, and dots';
                }

                if (preg_match('/\.\./', $value)) {
                    return 'S3 bucket name cannot contain consecutive dots';
                }

                if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $value)) {
                    return 'S3 bucket name cannot be formatted as an IP address';
                }

                return null;
            }
        );
    }

    /**
     * Generic method to collect replacement for a token.
     */
    private function collectGenericReplacement(ProjectReplacementToken $token): string
    {
        return text(
            label: filled($token->question())
                ? $token->question()
                : Str::of($token->name)->replace('_', ' ')->title()->toString(),
            placeholder: $token->placeholder(),
            required: true
        );
    }

    /**
     * Collect all files that need to be modified based on the tokens being replaced.
     */
    private function collectFilesToModify(): void
    {
        foreach (ProjectReplacementToken::cases() as $token) {
            // Skip tokens not being replaced
            if (! isset($this->replacements[$token->value])) {
                continue;
            }

            foreach ($token->filesToReplace() as $filePath) {
                if (File::exists($filePath) && ! in_array($filePath, $this->filesToModify, true)) {
                    $this->filesToModify[] = $filePath;
                }
            }
        }
    }

    /**
     * Backup files and prepare changes for preview.
     */
    private function backupAndPrepareChanges(): void
    {
        foreach ($this->filesToModify as $filePath) {
            // Backup original content
            $this->originalFileContents[$filePath] = File::get($filePath);
            File::copy($filePath, "{$filePath}.bak");

            // Prepare modified content
            $content = $this->originalFileContents[$filePath];
            foreach ($this->replacements as $token => $replacement) {
                $content = str_replace($token, $replacement, $content);
            }

            // Save to temporary file for diff comparison
            File::put("{$filePath}.new", $content);
            $this->tempFiles[] = "{$filePath}.new";
        }
    }

    /**
     * Show diff of changes and confirm with user.
     */
    private function showDiffAndConfirm(): bool
    {
        $this->components->info("\nPreviewing changes:\\n");

        foreach ($this->filesToModify as $filePath) {
            $newFilePath = "{$filePath}.new";

            if (File::exists($newFilePath)) {
                $process = Process::run(['git', 'diff', '--no-index', '--color=always', '--', $filePath, $newFilePath]);

                $diffOutput = $process->output();
                $exitCode = $process->exitCode();

                /**
                 * We only consider it a real failure if it's not exit code 0 (identical) or 1 (differences)
                 *
                 * {@link https://git-scm.com/docs/git-diff/2.28.0#Documentation/git-diff.txt---exit-code}
                 */
                if ($exitCode <= 1) {
                    if (! in_array(trim($diffOutput), ['', '0'], true)) {
                        $this->line($diffOutput);
                    } else {
                        $this->components->info('No changes needed in ' . basename($filePath));
                        $this->filesToModify[array_search($filePath, $this->filesToModify, true)] = null;
                    }
                } else {
                    $this->components->error('Failed to compare files: ' . $process->errorOutput());
                }
            }
        }

        if (array_filter($this->filesToModify) === []) {
            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        return confirm(
            label: 'Do you want to apply these changes?',
            hint: 'Applying these changes will modify your project files',
        );
    }

    /**
     * Apply all changes to files.
     */
    private function applyChanges(): void
    {
        foreach ($this->filesToModify as $filePath) {
            $newFilePath = "{$filePath}.new";

            if (File::exists($newFilePath)) {
                $this->components->task('Updating ' . basename($filePath), fn () => File::move($newFilePath, $filePath));
            }
        }
    }

    /**
     * Clean up temporary files created during the process.
     */
    private function cleanup(): void
    {
        // Clean up temporary .new files
        foreach ($this->tempFiles as $tempFile) {
            if (File::exists($tempFile)) {
                File::delete($tempFile);
            }
        }

        // Clean up .bak backup files
        foreach (ProjectReplacementToken::cases() as $token) {
            foreach ($token->filesToReplace() as $filePath) {
                $backupPath = "{$filePath}.bak";
                if (File::exists($backupPath)) {
                    File::delete($backupPath);
                }
            }
        }
    }
}
