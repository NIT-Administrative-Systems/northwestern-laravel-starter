<?php

declare(strict_types=1);

namespace App\Filament\Support\Formatting;

/**
 * Renders small Filament-style badge pills for custom HTML table summaries.
 */
class BadgePillRenderer
{
    /**
     * Render a badge pill with the requested Filament color scheme.
     */
    public function render(string $text, string $color, string $extraClasses = ''): string
    {
        $colors = match ($color) {
            'success' => 'fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20',
            'danger' => 'fi-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20',
            'warning' => 'fi-color-warning bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20',
            'primary' => 'fi-color-primary bg-primary-50 text-primary-600 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20',
            default => 'fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
        };

        $classes = 'fi-badge fi-size-sm inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' . $colors;

        if ($extraClasses !== '') {
            $classes .= ' ' . $extraClasses;
        }

        return '<span class="' . $classes . '">' . e($text) . '</span>';
    }
}
