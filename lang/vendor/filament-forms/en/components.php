<?php

declare(strict_types=1);

// Filament's labels that are names, in title case (see "Interface copy" in .github/copilot-instructions.md).
// Only these keys are overridden; the rest come from Filament. Check them when upgrading Filament.

return [
    'checkbox_list' => [
        'actions' => [
            'select_all' => [
                'label' => 'Select All',
            ],
            'deselect_all' => [
                'label' => 'Deselect All',
            ],
        ],
    ],
    'key_value' => [
        'actions' => [
            'add' => [
                'label' => 'Add Row',
            ],
        ],
    ],
    'select' => [
        'actions' => [
            'create_option' => [
                'modal' => [
                    'actions' => [
                        'create_another' => [
                            'label' => 'Create and Create Another',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
