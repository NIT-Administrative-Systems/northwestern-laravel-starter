<?php

declare(strict_types=1);

// Filament's labels that are names, in title case (see "Interface copy" in .github/copilot-instructions.md).
// Only these keys are overridden; the rest come from Filament. Check them when upgrading Filament.

return [
    'modal' => [
        'form' => [
            'columns' => [
                'actions' => [
                    'select_all' => [
                        'label' => 'Select All',
                    ],
                    'deselect_all' => [
                        'label' => 'Deselect All',
                    ],
                ],
            ],
        ],
    ],
    'notifications' => [
        'completed' => [
            'title' => 'Export Completed',
        ],
        'max_rows' => [
            'title' => 'Export Is Too Large',
        ],
        'no_columns' => [
            'title' => 'No Columns Selected',
        ],
        'started' => [
            'title' => 'Export Started',
        ],
    ],
];
