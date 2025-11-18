<?php

declare(strict_types=1);

namespace App\View\Components;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use UnitEnum;

/**
 * Tom Select component with search, multiple selection, and advanced features.
 *
 * This component provides a styled select element with search capabilities, multiple selection,
 * and other advanced features using the Tom Select library.
 *
 * <strong>Example</strong>
 * <code>
 * <x-select
 *     id="users"
 *     :options="$users"
 *     :settings="['selectOnTab' => true]"
 *     :plugins="['checkbox_options' => ['checkedClassNames' => ['custom-checkbox-checked']]]"
 *     search-url="/api/users/search"
 * />
 *
 * <x-select
 *     id="affiliation"
 *     :options="AffiliationEnum::cases()"
 *     :plugins="['checkbox_options', 'clear_button']"
 * />
 * </code>
 *
 * {@link https://tom-select.js.org}
 */
class Select extends Component
{
    /**
     * Available Tom Select plugins
     *
     * {@link https://tom-select.js.org/plugins}
     *
     * @var list<string>
     */
    public const PLUGINS = [
        'caret_position',
        'change_listener',
        'checkbox_options',
        'clear_button',
        'drag_drop',
        'dropdown_header',
        'dropdown_input',
        'input_autogrow',
        'no_active_items',
        'no_backspace_delete',
        'optgroup_columns',
        'remove_button',
        'restore_on_backspace',
        'virtual_scroll',
        // Custom Plugins
        'option_count',
    ];

    /** @var array<string, mixed> Validated plugin configurations. */
    protected array $validatedPlugins = [];

    /** @var string The plural noun for option counting. */
    public string $optionCountPluralNoun;

    /** @var int Default maximum number of options to display in the dropdown. */
    public const DEFAULT_MAX_OPTIONS = 30;

    /**
     * @param  string  $id  Unique identifier for the select element
     * @param  array<int|string, string|UnitEnum|array<int|string, string|UnitEnum>>|Collection<int|string, string|UnitEnum|array<int|string, string|UnitEnum>>  $options  Options for the select element (Collection, array, or associative array)
     * @param  array<string, mixed>  $settings  Additional Tom Select settings
     * @param  array<string|int, mixed>  $plugins  Plugin configurations
     * @param  bool  $searchable  Whether search functionality is enabled
     * @param  string  $searchUrl  URL for asynchronous search API
     * @param  string  $optionCountSingularNoun  Singular noun for option counting
     * @param  string|null  $optionCountPluralNoun  Plural noun for option counting (auto-generated if null)
     * @param  string|int|null  $maxOptions  Maximum number of options to display
     */
    public function __construct(
        public string $id,
        public mixed $options,
        public array $settings = [],
        public array $plugins = [],
        public bool $searchable = true,
        public string $searchUrl = '',
        public string $optionCountSingularNoun = 'Item',
        ?string $optionCountPluralNoun = null,
        public string|int|null $maxOptions = self::DEFAULT_MAX_OPTIONS,
    ) {
        if ($optionCountPluralNoun === null || $optionCountPluralNoun === '' || $optionCountPluralNoun === '0') {
            $optionCountPluralNoun = Str::plural($this->optionCountSingularNoun);
        }

        $this->optionCountPluralNoun = $optionCountPluralNoun;

        $this->options = self::processOptions($this->options);

        $this->registerPlugins();
    }

    /**
     * Get the complete Tom Select configuration.
     *
     * @return array<string, mixed> The configuration array for Tom Select
     */
    public function getTomSelectConfig(): array
    {
        $enableAsyncSearch = filled($this->searchUrl);

        return [
            'hidePlaceholder' => true,
            'loadThrottle' => 250,
            'maxOptions' => $this->maxOptions(),
            'onItemAdd' => true,
            'onInitialize' => true,
            'searchDisabled' => ! $this->searchable,
            'load' => $enableAsyncSearch ? true : null,
            'searchUrl' => $this->searchUrl,
            'plugins' => $this->validatedPlugins === [] ? null : $this->validatedPlugins,
            ...$this->settings,
        ];
    }

    public function render(): View
    {
        return view('components.select');
    }

    public function maxOptions(): ?int
    {
        if ($this->maxOptions === null) {
            return null;
        }

        return is_string($this->maxOptions) ? (int) $this->maxOptions : $this->maxOptions;
    }

    /**
     * Process various types of input options into a consistent key-value array format.
     *
     * Supports:
     * - Arrays of strings: ['option1', 'option2']
     * - Associative arrays: ['key1' => 'Label 1', 'key2' => 'Label 2']
     * - Collections (converted to arrays)
     * - UnitEnum cases: Enum::CASE->name becomes the option key
     * - BackedEnum cases: Enum::CASE->value becomes the option key
     * - Mixed arrays containing enum instances
     *
     * @return array<string|int, string|array<string|int, string>|mixed> Processed options as key-value pairs
     */
    public static function processOptions(mixed $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        if ($options instanceof Collection) {
            $options = $options->toArray();
        }

        $processedOptions = [];
        $isSequentialNumericList = array_is_list($options);

        foreach ($options as $key => $value) {
            if ($value instanceof UnitEnum) {
                // Always use enum key as option value
                $enumKey = $value instanceof BackedEnum ? $value->value : $value->name;
                $processedOptions[$enumKey] = self::getEnumLabel($value);
            } elseif (is_array($value)) {
                // Support nested optgroups
                $processedOptions[$key] = self::processOptions($value);
            } elseif ($isSequentialNumericList && is_int($key)) {
                // For pure numeric sequential lists, use value as both key and label
                $processedOptions[$value] = (string) $value;
            } else {
                // Explicit key was provided, or numeric key from model plucks
                $processedOptions[$key] = (string) $value;
            }
        }

        return $processedOptions;
    }

    /**
     * Get a user-friendly label for an enum.
     */
    private static function getEnumLabel(UnitEnum $enum): string
    {
        return method_exists($enum, 'label')
            ? $enum->label()
            : Str::title(str_replace(['-', '_'], ' ', $enum->name));
    }

    /**
     * Validate the plugin configurations against {@see self::PLUGINS}.
     */
    protected function registerPlugins(): void
    {
        static $pluginSet = null;
        if ($pluginSet === null) {
            $pluginSet = array_flip(self::PLUGINS);
        }

        foreach ($this->plugins as $key => $value) {
            if (is_int($key) && is_string($value)) {
                // Simple array of plugin names
                if (isset($pluginSet[$value])) {
                    $this->validatedPlugins[$value] = $this->getDefaultPluginConfig($value);
                }
            } elseif (is_string($key) && isset($pluginSet[$key])) {
                // Associative array with configurations
                if ($value === true) {
                    // If just 'true', apply default config
                    $this->validatedPlugins[$key] = $this->getDefaultPluginConfig($key);
                } else {
                    // Merge provided config with defaults
                    $defaultConfig = $this->getDefaultPluginConfig($key);
                    $this->validatedPlugins[$key] = is_array($defaultConfig)
                        ? array_merge($defaultConfig, (array) $value)
                        : $value;
                }
            }
        }
    }

    /**
     * Get default configuration for a specific plugin.
     *
     * @param  string  $plugin  Plugin name
     * @return array<string, mixed>|bool Default configuration
     */
    private function getDefaultPluginConfig(string $plugin): array|bool
    {
        $defaults = [
            'remove_button' => ['title' => 'Remove this item'],
            'clear_button' => ['title' => 'Remove all selected options'],
            'option_count' => [
                'singularNoun' => $this->optionCountSingularNoun,
                'pluralNoun' => $this->optionCountPluralNoun,
            ],
        ];

        return $defaults[$plugin] ?? true;
    }
}
