<?php

declare(strict_types=1);

namespace App\Domains\Core\Seeders;

use App\Domains\Core\Contracts\IdempotentSeederInterface;
use App\Domains\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;

/**
 * Base class for production-safe, idempotent database seeders.
 *
 * Unlike traditional seeders that insert new records every time they run,
 * idempotent seeders safely upsert data based on a unique identifier column.
 * This makes them safe to run multiple times without creating duplicates.
 *
 * Key features:
 * - Upserts records based on a slug column (create if missing, update if exists)
 * - Handles soft-deleted models (restores them instead of creating duplicates)
 * - Optionally deletes records that are no longer in the seed data
 * - Production-safe (can run on existing databases without breaking anything)
 *
 * @see IdempotentSeederInterface
 * @see \App\Domains\Core\Attributes\AutoSeed
 */
abstract class IdempotentSeeder extends Seeder implements IdempotentSeederInterface
{
    /**
     * Model to use for select/update/insert/delete operations.
     *
     * @return class-string<BaseModel>
     */
    protected string $model;

    /**
     * The column name used to identify existing records.
     *
     * This column should contain unique identifiers that remain
     * stable across environments (e.g., 'slug', 'code', 'name').
     */
    protected string $slugColumn;

    public function run(): void
    {
        $touchedSlugs = [];

        foreach ($this->data() as $row) {
            $row = collect($row);

            /** @var Builder<BaseModel> $builder */
            $builder = $this->model::query();

            // For models using SoftDelete, un-deleting requires some special handling: the query needs to include
            // soft-deleted models, and the update needs to reset the deleted_at key.
            if (in_array(SoftDeletes::class, (array) class_uses($this->model), true)) {
                /** @var Builder<BaseModel> $builder */
                $builder = $this->model::withTrashed();

                /** @var BaseModel $modelInstance */
                $modelInstance = new $this->model();

                $deletedColumn = method_exists($modelInstance, 'getDeletedAtColumn')
                    ? $modelInstance->getDeletedAtColumn()
                    : 'deleted_at';

                $row->put($deletedColumn, null);
            }

            $builder->updateOrCreate(
                $row->only($this->slugColumn)->all(),
                $row->except($this->slugColumn)->all(),
            );

            $touchedSlugs[] = $row->get($this->slugColumn);
        }

        $this->model::whereNotIn($this->slugColumn, $touchedSlugs)->get()->each->delete();
    }

    /**
     * @return array<array<string, mixed>>
     */
    abstract public function data(): array;
}
