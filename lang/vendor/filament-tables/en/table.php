<?php

declare(strict_types=1);

// Filament's labels that are names, in title case (see "Interface copy" in .github/copilot-instructions.md).
// Only these keys are overridden; the rest come from Filament. Check them when upgrading Filament.

return [
    'column_manager' => [
        'actions' => [
            'apply' => [
                'label' => 'Apply Columns',
            ],
        ],
    ],
    'actions' => [
        'column_manager' => [
            'label' => 'Column Manager',
        ],
        'open_bulk_actions' => [
            'label' => 'Bulk Actions',
        ],
        'enable_reordering' => [
            'label' => 'Reorder Records',
        ],
        'disable_reordering' => [
            'label' => 'Finish Reordering Records',
        ],
    ],
    'filters' => [
        'actions' => [
            'apply' => [
                'label' => 'Apply Filters',
            ],
            'remove' => [
                'label' => 'Remove Filter',
            ],
            'remove_all' => [
                'label' => 'Remove All Filters',
                'tooltip' => 'Remove All Filters',
            ],
        ],
        'indicator' => 'Active Filters',
    ],
    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'Group By',
            ],
            'direction' => [
                'label' => 'Group Direction',
            ],
        ],
    ],
    'sorting' => [
        'fields' => [
            'column' => [
                'label' => 'Sort By',
            ],
            'direction' => [
                'label' => 'Sort Direction',
            ],
        ],
    ],
    'selection_indicator' => [
        'actions' => [
            'select_all' => [
                'label' => 'Select All :count',
            ],
            'deselect_all' => [
                'label' => 'Deselect All',
            ],
        ],
    ],
];
