<?php

namespace Devespresso\DataFiltering\Services\Filters;

use Devespresso\DataFiltering\Transformers\BaseTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;
use ReflectionClass;

class BaseFilterService
{
    /**
     * Set the transformer
     *
     * @var string
     */
    protected $transformer = null;

    /**
     * Select
     *
     * @var array
     */
    protected $select = [];

    /**
     * User
     *
     * @var User
     */
    protected $user = null;

    /**
     * Model
     *
     * @var Model
     */
    protected $model = null;

    /**
     * Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Extra properties
     *
     * @var array
     */
    protected $extras = [];

    /**
     * Builder
     *
     * @var Builder
     */
    protected $query = null;

    /**
     * Allow sorting
     *
     * @var array
     */
    protected $sortColumns = [
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * Custom Sort Columns
     *
     * @var array
     */
    protected $customSortColumns = [];

    /**
     * Sets the default sorting
     *
     * @var string
     */
    protected $defaultSortingColumn = ['id,desc'];

    /**
     * Methods that cannot be triggered by the data
     *
     * @var array
     */
    protected $guardedMethods = [];

    /**
     * Methods that admin users can trigger
     *
     * @var array
     */
    protected $adminMethods = [];

    /**
     * Methods that are going to be applied
     *
     * @var array
     */
    protected $autoApply = [];

    /**
     * Run conditions
     *
     * @var bool
     */
    protected $runConditions = true;

    /**
     * Sets the user
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Sets the data
     */
    public function setData(array $data = []): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Sets the data
     *
     * @param  array  $data
     */
    public function setExtras(array $extras = []): self
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Sets the query
     *
     * @param  Builder  $query
     */
    public function setQuery($query): self
    {
        if (! $this->query) {
            $this->query = $query;
        }

        return $this;
    }

    /**
     * Sets the model
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Sets conditions
     */
    protected function setConditions(): void
    {
    }

    /**
     * Sets the filters
     */
    protected function setFilters(
        array $filters,
        array $guarded = []
    ): void {
        foreach ($filters as $method => $value) {
            $method = Str::camel($method);
            if (! in_array($method, $guarded) && method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Adds the select
     */
    public function setSelect(array $value = []): void
    {
        $this->select = count($value) ? $value : $this->select;
    }

    /**
     * Disables the conditions
     */
    public function disableConditions(): void
    {
        $this->runConditions = false;
    }

    /**
     * Start filtering
     *
     * @return Builder
     */
    public function filter()
    {
        $this->setQuery($this->model->query());

        $this->addSelectBasedOnTransformer();

        if (count($this->select)) {
            $this->query->select($this->select);
        }

        if ($this->runConditions) {
            $this->setConditions();
        }

        // Apply filters
        $this->setFilters($this->data, [
            ...$this->getBaseGuardedMethods(),
            ...$this->guardedMethods,
            ...optional($this->user)->isAdmin() ? [] : $this->adminMethods,
        ]);

        if (! isset($this->data['sort'])) {
            $this->sort($this->defaultSortingColumn);
        }

        // Apply automatic filters
        $this->setFilters($this->autoApply);

        $paginateMethod = $this->getPaginationMethod(
            $this->getDataValue('with_pages')
        );

        return $this->query->$paginateMethod($this->getPerPage());
    }

    /**
     * Search specific table
     */
    public function search(string $search): void
    {
        $this->query->search($search);
    }

    /**
     * Sorting
     *
     * @param  string|array  $sort
     */
    public function sort(array|string $sortValues, bool $hasBeenRenamed = false): self
    {
        $sortValues = is_iterable($sortValues) ? $sortValues : [$sortValues];
        foreach ($sortValues as $sort) {
            $this->applySort($sort, $hasBeenRenamed);
        }

        return $this;
    }

    /**
     * Apply sort
     */
    public function applySort(string $sort, bool $hasBeenRenamed = false): void
    {
        // Extract the values
        [$column, $order] = array_pad(explode(',', $sort), 2, 'desc');
        // Sets the order
        $order = $order === 'asc' ? 'asc' : 'desc';
        // Allowed columns
        $allowedColumns = array_merge($this->sortColumns, $this->customSortColumns);
        // Check if it is a renamed column
        if (array_key_exists($column, $allowedColumns)) {
            $this->sort($allowedColumns[$column], $hasBeenRenamed);

            return;
        }
        // Check if the order is allowed
        if (! in_array($column, $allowedColumns) && ! $hasBeenRenamed) {
            $this->sort($this->defaultSortingColumn);

            return;
        }
        $this->query->orderBy($column, $order);
    }

    /**
     * Sets relations to eager load
     */
    protected function with(array $data): void
    {
        $this->query->with($data);
    }

    /**
     * Sets count for relationships
     */
    protected function withCount(array $data): void
    {
        $this->query->withCount($data);
    }

    /**
     * Checks if value exists
     *
     * @param  mixed  $value
     */
    public function dataHasValue(string $key, $value): bool
    {
        if (array_key_exists($key, $this->data)) {
            return false;
        }

        return $this->data[$key] === $value;
    }

    /**
     * Gets data value
     *
     * @return mixed
     */
    public function getDataValue(string $key, $default = null)
    {
        if (! $this->dataHasKeys([$key])) {
            return $default;
        }

        return $this->data[$key];
    }

    /**
     * Checks if keys exists
     */
    public function dataHasKeys(array $keys = []): bool
    {
        $dataKeys = array_keys($this->data);

        return count(array_intersect($dataKeys, $keys)) === count($keys);
    }

    /**
     * Gets the property
     */
    public function getExtraProperty(string $property): mixed
    {
        return optional($this->extras)[$property];
    }

    /**
     * Adds the select based on the transformer
     */
    protected function addSelectBasedOnTransformer(): void
    {
        $transformer = $this->guessTransformer();
        $format = $transformer->getFormatting();
        $this->addSelectAndEagerLoad($this->query, $format);
    }

    /**
     * Adds select and eager loads relationship
     *
     * @param  Builder  $query
     * @param  array  $format
     */
    protected function addSelectAndEagerLoad($query, $format): void
    {
        if (config('apix.auto_select')) {
            $columns = array_filter($format, function ($item) {
                return ! is_array($item);
            });
            if (! count($columns)) {
                $columns = ['*'];
            }
            $query->select(
                array_filter(array_map(function ($item) {
                    return Str::replaceFirst(
                        config('apix.transformers.prefixes.hidden_attributes'),
                        '',
                        $item
                    );
                }, $columns), function ($item) {
                    return ! Str::startsWith(
                        $item,
                        config('apix.transformers.prefixes.custom_attributes')
                    );
                })
            );
        }
        if (config('apix.auto_eager_load')) {
            foreach ($format as $relation => $select) {
                if (! is_array($select)) {
                    continue;
                }
                $query->with($relation, function ($query) use ($select) {
                    $this->addSelectAndEagerLoad($query, $select);
                });
            }
        }
    }

    /**
     * Gets the transformer
     */
    protected function guessTransformer(): BaseTransformer
    {
        if ($this->transformer) {
            return resolve($this->transformer);
        }

        $transformer = (string) Str::of(class_basename($this->model))
            ->prepend(config('apix.paths.transformers'))
            ->append('Transformer');

        return resolve($transformer);
    }

    /**
     * Get base guarded methods
     */
    protected function getBaseGuardedMethods(): array
    {
        $class = new ReflectionClass(BaseFilterService::class);

        return array_filter(array_map(function ($method) {
            return $method->getName();
        }, $class->getMethods()), function ($item) {
            return ! in_array($item, ['sort', 'search']);
        });
    }

    /**
     * Get
     */
    protected function getPaginationMethod(?bool $withPages = null): string
    {
        if (is_null($withPages)) {
            return config('apix.pagination.with_pages') ? 'paginate' : 'simplePaginate';
        }

        return $withPages ? 'paginate' : 'simplePaginate';
    }

    /**
     * Get page
     */
    protected function getPage(): int
    {
        return $this->getDataValue('page', 1);
    }

    /**
     * Gets per page
     */
    protected function getPerPage(): int
    {
        return $this->getDataValue('per_page', $this->model->getPerPage());
    }
}
