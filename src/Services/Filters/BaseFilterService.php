<?php

namespace Abix\DataFiltering\Services\Filters;

use Abix\DataFiltering\Transformers\BaseTransformer;
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
    protected $defaultSortingColumn = ['id,desc', 'created_at'];

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
     *
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Sets the data
     *
     * @param array $data
     * @return self
     */
    public function setData(array $data = []): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Sets the data
     *
     * @param array $data
     * @return self
     */
    public function setExtras(array $extras = []): self
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Sets the query
     *
     * @param Builder $query
     * @return self
     */
    public function setQuery($query): self
    {
        if (!$this->query) {
            $this->query = $query;
        }

        return $this;
    }

    /**
     * Sets the model
     *
     * @param Model $model
     * @return self
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Sets conditions
     *
     * @return self
     */
    public function setConditions(): self
    {
        return $this;
    }

    /**
     * Sets the filters
     *
     * @param array $filters
     * @param array $guarded
     * @return void
     */
    protected function setFilters(
        array $filters,
        array $guarded = []
    ): void {
        foreach ($filters as $method => $value) {
            $method = Str::camel($method);
            if (!in_array($method, $guarded) && method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Adds the select
     *
     * @param array $value
     * @return self
     */
    public function setSelect(array $value = []): self
    {
        $this->select = count($value) ? $value : $this->select;

        return $this;
    }

    /**
     * Disables the conditions
     *
     * @return self
     */
    public function disableConditions(): self
    {
        $this->runConditions = false;

        return $this;
    }

    /**
     * Start filtering
     *
     * @return Builder
     */
    public function filter()
    {
        $this->setQuery($this->model->query());

        if ($this->runConditions) {
            $this->setConditions();
        }

        // Apply filters
        $this->setFilters($this->data, [
            ...$this->getBaseGuardedMethods(),
            ...$this->guardedMethods,
            ...optional($this->user)->isAdmin() ? [] : $this->adminMethods,
        ]);

        if (!isset($this->data['sort'])) {
            $this->sort($this->defaultSortingColumn);
        }

        // Apply automatic filters
        $this->setFilters($this->autoApply);

        $this->addSelectBasedOnTransformer();

        if (count($this->select)) {
            $this->query->select($this->select);
        }

        $paginateMethod = $this->getPaginationMethod(
            $this->getDataValue('with_pages')
        );

        return $this->query->$paginateMethod($this->getPerPage());
    }

    /**
     * Search specific table
     *
     * @param string $search
     * @return self
     */
    public function search(string $search): self
    {
        $this->query->search($search);

        return $this;
    }

    /**
     * Sorting
     *
     * @param string|array $sort
     * @return self
     */
    public function sort($sortValues): self
    {
        $sortValues = is_iterable($sortValues) ? $sortValues : [$sortValues];

        foreach ($sortValues as $sort) {
            $this->applySort($sort);
        }

        return $this;
    }

    /**
     * Apply sort
     *
     * @param string $sort
     * @return self
     */
    public function applySort(string $sort): self
    {
        [$originalColumn, $originalOrder] = $this->getSortValues($sort);

        $column = $originalColumn;

        $order = $originalOrder === 'asc' ? 'asc' : 'desc';

        $allowedColumns = array_merge($this->sortColumns, $this->customSortColumns);

        if (!in_array($originalColumn, $allowedColumns)) {
            [$column, $newOrder] = $this->getSortValues($this->defaultSortingColumn);
        }

        if (isset($allowedColumns[$originalColumn])) {
            [$column, $newOrder] = $this->getSortValues(
                $allowedColumns[$originalColumn]
            );
        }

        if (!$originalOrder && isset($newOrder)) {
            $order = $newOrder;
        }

        $this->query->orderBy($column, $order);

        return $this;
    }

    /**
     * Extract values
     *
     * @param string $sort
     * @return array
     */
    private function getSortValues(string $sort): array
    {
        return array_pad(explode(',', $sort), 2, null);
    }

    /**
     * Sets relations to eager load
     *
     * @param array $data
     * @return self
     */
    protected function with(array $data): self
    {
        $this->query->with($data);

        return $this;
    }

    /**
     * Sets count for relationships
     *
     * @param array $data
     * @return self
     */
    protected function withCount(array $data): self
    {
        $this->query->withCount($data);

        return $this;
    }

    /**
     * Checks if value exists
     *
     * @param string $key
     * @param mixed $value
     * @return boolean
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
     * @param string $key
     * @return mixed
     */
    public function getDataValue(string $key, $default = null)
    {
        if (!$this->dataHasKeys([$key])) {
            return $default;
        }

        return $this->data[$key];
    }

    /**
     * Checks if keys exists
     *
     * @param array $keys
     * @return boolean
     */
    public function dataHasKeys(array $keys = []): bool
    {
        $dataKeys = array_keys($this->data);

        return count(array_intersect($dataKeys, $keys)) === count($keys);
    }

    /**
     * Gets the property
     *
     * @param string $property
     * @return mixed
     */
    public function getExtraProperty(string $property): mixed
    {
        return optional($this->extras)[$property];
    }

    /**
     * Adds the select based on the transformer
     *
     * @return void
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
     * @param Builder $query
     * @param array $format
     * @return void
     */
    protected function addSelectAndEagerLoad($query, $format): void
    {
        if (config('apix.auto_select')) {
            $columns = array_filter($format, function ($item) {
                return !is_array($item);
            });

            if (!count($columns)) {
                $columns = ['*'];
            }

            $query->select(array_map(function ($item) {
                return Str::replaceFirst(':', '', $item);
            }, $columns));
        }

        if (config('apix.auto_eager_load')) {
            $relations = array_filter($format, function ($item) {
                return is_array($item);
            });

            foreach ($relations as $relation => $select) {
                $query->with($relation, function ($query) use ($select) {
                    $this->addSelectAndEagerLoad($query, $select);
                });
            }
        }
    }

    /**
     * Gets the transformer
     *
     * @return BaseTransformer
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
     *
     * @return array
     */
    protected function getBaseGuardedMethods(): array
    {
        $class = new ReflectionClass(BaseFilterService::class);

        return array_filter(array_map(function ($method) {
            return $method->getName();
        }, $class->getMethods()), function ($item) {
            return !in_array($item, ['sort', 'search']);
        });
    }

    /**
     * Get
     *
     * @param boolean|null $withPages
     * @return string
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
     *
     * @return int
     */
    protected function getPage(): int
    {
        return $this->getDataValue('page', 1);
    }

    /**
     * Gets per page
     *
     * @return int
     */
    protected function getPerPage(): int
    {
        return $this->getDataValue('per_page', $this->model->getPerPage());
    }
}
