<?php

declare(strict_types=1);

namespace App\Domains\Core\Data;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Typed settings stored in a JSON column. Each preference is a promoted constructor property
 * with a default, so adding one is a single line: no migration, no backfill.
 *
 * Reading is forgiving, because the JSON outlives the code: a key with no matching property is
 * dropped, and a missing or wrongly typed value falls back to its default. Writing stores every
 * property, so the column always reflects what the code means.
 *
 * Cast a column with the subclass itself: `'preferences' => UserPreferences::class`.
 */
abstract readonly class Preferences implements Castable
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): static
    {
        $arguments = [];

        foreach (self::parameters() as $parameter) {
            $name = $parameter->getName();

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $value = self::coerce($parameter, $values[$name]);

            if ($value !== null || ($values[$name] === null && $parameter->allowsNull())) {
                $arguments[$name] = $value;
            }
        }

        // Through reflection, because each subclass declares its own constructor.
        return (new ReflectionClass(static::class))->newInstanceArgs($arguments);
    }

    /**
     * A copy with some preferences changed. Changes go through the same coercion as stored values.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): static
    {
        return static::fromArray([...$this->toArray(), ...$changes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value,
            get_object_vars($this),
        );
    }

    /**
     * @param  array<mixed>  $arguments
     * @return CastsAttributes<static, static|array<string, mixed>>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        $class = static::class;

        return new class($class) implements CastsAttributes
        {
            /**
             * @param  class-string<Preferences>  $class
             */
            public function __construct(private readonly string $class)
            {
            }

            public function get(Model $model, string $key, mixed $value, array $attributes): Preferences
            {
                $decoded = is_string($value) ? json_decode($value, associative: true) : null;

                return $this->class::fromArray(is_array($decoded) ? $decoded : []);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): string
            {
                $preferences = match (true) {
                    $value instanceof $this->class => $value,
                    is_array($value) => $this->class::fromArray($value),
                    $value === null => $this->class::fromArray([]),
                    default => throw new InvalidArgumentException("{$key} must be a {$this->class} or an array."),
                };

                // As an object, so no preferences is `{}` rather than `[]`; list values stay lists.
                return json_encode((object) $preferences->toArray(), JSON_THROW_ON_ERROR);
            }
        };
    }

    /**
     * @return list<ReflectionParameter>
     */
    private static function parameters(): array
    {
        return (new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [];
    }

    /**
     * The value as the parameter's type, or null when it can't be one.
     */
    private static function coerce(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();

        if ($value === null || ! $type instanceof ReflectionNamedType) {
            return null;
        }

        $typeName = $type->getName();

        if (is_subclass_of($typeName, BackedEnum::class)) {
            return $value instanceof $typeName ? $value : (is_int($value) || is_string($value) ? $typeName::tryFrom($value) : null);
        }

        return match ($typeName) {
            'bool' => is_bool($value) ? $value : null,
            'int' => is_int($value) ? $value : null,
            'float' => is_int($value) || is_float($value) ? (float) $value : null,
            'string' => is_string($value) ? $value : null,
            'array' => is_array($value) ? $value : null,
            default => null,
        };
    }
}
