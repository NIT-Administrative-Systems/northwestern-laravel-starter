<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Select::class)]
final class SelectTest extends TestCase
{
    public function test_renders_select_view(): void
    {
        $component = new Select('test', []);

        $this->assertSame('components.select', $component->render()->name());
    }

    public function test_plugin_validation_with_default_config(): void
    {
        $component = new Select(
            id: 'test',
            options: [],
            plugins: ['remove_button', 'clear_button'],
        );

        $expectedPlugins = [
            'remove_button' => ['title' => 'Remove this item'],
            'clear_button' => ['title' => 'Remove all selected options'],
        ];

        $this->assertSame($expectedPlugins, $component->getTomSelectConfig()['plugins']);
    }

    public function test_plugin_configuration_override(): void
    {
        $component = new Select(
            id: 'test',
            options: [],
            plugins: [
                'remove_button' => ['title' => 'Custom Remove'],
                'option_count' => ['singularNoun' => 'User', 'pluralNoun' => 'Users'],
            ],
            optionCountSingularNoun: 'DefaultItem'
        );

        $expectedPlugins = [
            'remove_button' => ['title' => 'Custom Remove'],
            'option_count' => ['singularNoun' => 'User', 'pluralNoun' => 'Users'],
        ];

        $this->assertSame($expectedPlugins, $component->getTomSelectConfig()['plugins']);
    }

    public function test_custom_settings_override_defaults(): void
    {
        $customSettings = [
            'hidePlaceholder' => false,
            'loadThrottle' => 500,
            'customSetting' => 'value',
        ];

        $component = new Select('test', [], settings: $customSettings);
        $config = $component->getTomSelectConfig();

        $this->assertFalse($config['hidePlaceholder']);
        $this->assertEquals(500, $config['loadThrottle']);
        $this->assertEquals('value', $config['customSetting']);
    }

    public function test_max_options_default_and_custom(): void
    {
        $componentDefault = new Select('test', []);
        $componentCustom = new Select('test', [], maxOptions: 50);

        $this->assertSame(30, $componentDefault->maxOptions());
        $this->assertSame(50, $componentCustom->maxOptions());
    }

    public function test_option_count_pluralization(): void
    {
        $componentDefault = new Select('test', [], optionCountSingularNoun: 'Box');
        $componentExplicit = new Select('test', [], optionCountSingularNoun: 'Box', optionCountPluralNoun: 'Crates');

        $this->assertSame('Boxes', $componentDefault->optionCountPluralNoun);
        $this->assertSame('Crates', $componentExplicit->optionCountPluralNoun);
    }

    public function test_async_search_configuration(): void
    {
        $componentWithoutUrl = new Select('test', []);
        $componentWithUrl = new Select('test', [], searchUrl: '/api/search');

        $this->assertNull($componentWithoutUrl->getTomSelectConfig()['load']);
        $this->assertTrue($componentWithUrl->getTomSelectConfig()['load']);
    }

    public function test_simple_indexed_array_options(): void
    {
        $options = ['active', 'pending'];

        $expected = [
            'active' => 'active',
            'pending' => 'pending',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_associative_array_options(): void
    {
        $options = ['active' => 'Active', 'pending' => 'Pending'];

        $this->assertSame($options, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_collection_options(): void
    {
        $options = collect(['active', 'pending']);

        $expected = [
            'active' => 'active',
            'pending' => 'pending',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_model_collection_id_and_label(): void
    {
        $users = collect([1 => 'Foo', 2 => 'Bar']);

        $expected = [
            1 => 'Foo',
            2 => 'Bar',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($users));
    }

    public function test_nested_options(): void
    {
        $options = [
            'Status' => ['active' => 'Active', 'pending' => 'Pending'],
            'Special' => ['banned' => 'Banned'],
        ];

        $this->assertSame($options, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_unit_enum_options(): void
    {
        $options = FakeEnum::cases();
        $expected = [
            'Active' => 'Active',
            'Pending' => 'Pending',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_backed_enum_options(): void
    {
        $options = FakeBackedEnum::cases();
        $expected = [
            'active' => 'Active',
            'pending' => 'Pending',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_mixed_array_with_enums(): void
    {
        $options = [
            FakeBackedEnum::Active,
            'custom' => 'Custom Label',
            FakeEnum::Pending,
        ];

        $expected = [
            'active' => 'Active',
            'custom' => 'Custom Label',
            'Pending' => 'Pending',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_numeric_list_options(): void
    {
        $options = [1, 2, 3];

        $expected = [
            1 => '1',
            2 => '2',
            3 => '3',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_nested_enum_options(): void
    {
        $options = [
            'Group 1' => FakeEnum::cases(),
            'Group 2' => FakeBackedEnum::cases(),
        ];

        $expected = [
            'Group 1' => [
                'Active' => 'Active',
                'Pending' => 'Pending',
            ],
            'Group 2' => [
                'active' => 'Active',
                'pending' => 'Pending',
            ],
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_empty_options(): void
    {
        $this->assertSame([], Select::normalizeOptionsToKeyValuePairs([]));
        $this->assertSame([], Select::normalizeOptionsToKeyValuePairs(collect()));
    }

    public function test_null_options(): void
    {
        $this->assertSame([], Select::normalizeOptionsToKeyValuePairs(null));
    }

    public function test_collection_of_models_directly(): void
    {
        $users = collect([1 => 'Alice', 2 => 'Bob']);

        $expected = [
            1 => 'Alice',
            2 => 'Bob',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($users));
    }

    public function test_max_options_returns_null_when_set_to_null(): void
    {
        $component = new Select('test', [], maxOptions: null);

        $this->assertNull($component->maxOptions());
        $this->assertNull($component->getTomSelectConfig()['maxOptions']);
    }

    public function test_max_options_casts_string_to_int(): void
    {
        $component = new Select('test', [], maxOptions: '75');

        $this->assertSame(75, $component->maxOptions());
    }

    public function test_plugin_with_true_value_applies_default_config(): void
    {
        $component = new Select(
            id: 'test',
            options: [],
            plugins: ['checkbox_options' => true],
        );

        $this->assertTrue($component->getTomSelectConfig()['plugins']['checkbox_options']);
    }

    public function test_plugin_config_override_with_non_array_default(): void
    {
        $component = new Select(
            id: 'test',
            options: [],
            plugins: ['no_active_items' => ['custom' => 'value']],
        );

        $this->assertSame(['custom' => 'value'], $component->getTomSelectConfig()['plugins']['no_active_items']);
    }

    public function test_enum_without_label_method_uses_name_fallback(): void
    {
        $options = FakeEnumWithoutLabel::cases();

        $expected = [
            'SomeValue' => 'Some Value',
            'AnotherItem' => 'Another Item',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }

    public function test_backed_enum_without_label_method_uses_name_fallback(): void
    {
        $options = FakeBackedEnumWithoutLabel::cases();

        $expected = [
            'sv' => 'Some Value',
            'ai' => 'Another Item',
        ];

        $this->assertSame($expected, Select::normalizeOptionsToKeyValuePairs($options));
    }
}

enum FakeEnumWithoutLabel
{
    case SomeValue;
    case AnotherItem;
}

enum FakeBackedEnumWithoutLabel: string
{
    case SomeValue = 'sv';
    case AnotherItem = 'ai';
}

enum FakeEnum
{
    case Active;
    case Pending;

    public function label(): string
    {
        return ucfirst(strtolower($this->name));
    }
}

enum FakeBackedEnum: string
{
    case Active = 'active';
    case Pending = 'pending';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
