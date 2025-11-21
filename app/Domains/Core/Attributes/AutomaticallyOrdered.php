<?php

declare(strict_types=1);

namespace App\Domains\Core\Attributes;

use Attribute;

/**
 * Automatically orders query results by specified columns.
 *
 * This attribute applies a global scope to a model that orders results first by the primary
 * column, then by the secondary column. By default, models are ordered by `order_index`
 * and then `label` in ascending order.
 *
 * Basic usage:
 * <code>
 * #[AutomaticallyOrdered]
 * class Category extends BaseModel
 * {
 *     // Will order by order_index asc, then label asc
 * }
 * </code>
 *
 * Custom columns:
 * <code>
 * #[AutomaticallyOrdered(primary: 'stock_quantity', secondary: 'name')]
 * class Product extends BaseModel
 * {
 *     // Will order by stock_quantity asc, then name asc
 * }
 * </code>
 *
 * Custom sort directions:
 * <code>
 * #[AutomaticallyOrdered(primary: 'created_at', primaryDirection: 'desc')]
 * class Article extends BaseModel
 * {
 *     // Will order by created_at desc, then label asc
 * }
 * </code>
 *
 * To disable automatic ordering for a specific query:
 * <code>
 * Category::withoutGlobalScope(AutomaticallyOrderedScope::class)->get();
 * // OR
 * Category::withoutGlobalScopes()->get();
 * </code>
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AutomaticallyOrdered
{
    /**
     * @param  string  $primary  The primary column to order by (default: 'order_index')
     * @param  'asc'|'desc'  $primaryDirection  Sort direction for primary column (default: 'asc')
     * @param  string  $secondary  The secondary column to order by (default: 'label')
     * @param  'asc'|'desc'  $secondaryDirection  Sort direction for secondary column (default: 'asc')
     */
    public function __construct(
        public string $primary = 'order_index',
        public string $primaryDirection = 'asc',
        public string $secondary = 'label',
        public string $secondaryDirection = 'asc',
    ) {
        //
    }
}
