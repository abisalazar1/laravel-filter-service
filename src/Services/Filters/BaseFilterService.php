<?php

namespace Abix\DataFiltering\Services\Filters;

use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;

class BaseFilterService
{
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
    protected $defaultSortingColumn = 'id';

    /**
     * Methods that cannot be triggered by the data
     *
     * @var array
     */
    protected $guardedMethods = [
        'setModel',
        'setUser',
        'setData',
        'setQuery',
        'setExtras',
        'setSelect',
        'applySort',
        'setConditions',
        'setFilters',
        'filter',
        'with',
        'withCount',
        'disableConditions',
    ];

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

        if (!optional($this->user)->isAdmin()) {
            $this->guardedMethods = array_merge(
                $this->guardedMethods,
                $this->adminMethods
            );
        }

        if ($this->runConditions) {
            $this->setConditions();
        }

        // Apply filters
        $this->setFilters($this->data, $this->guardedMethods);

        if (!isset($this->data['sort'])) {
            $this->sort($this->defaultSortingColumn);
        }

        // Apply automatic filters
        $this->setFilters($this->autoApply);

        if (count($this->select)) {
            $this->query->select($this->select);
        }

        return $this->query;
    }

    /**
     * Search specific table
     *
     * @param string $serach
     * @return self
     */
    public function search(string $serach): self
    {
        $this->query->search($serach);

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
     * Sets relations to eagerload
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
     * @param mix $value
     * @return boolean
     */
    public function filterHasValue(string $key, $value):bool
    {
        if (array_key_exists($key, $this->data)) {
            return false;
        }

        return $this->data[$key] === $value;
    }

    /**
     * Checks if keys exists
     *
     * @param array $keys
     * @return boolean
     */
    public function filterHasKeys(array $keys = []):bool
    {             
        $dataKeys = array_keys($this->data);

        return count(array_intersect($dataKeys, $keys)) === count($keys);
    }

    /**
     * Undocumented function
     *
     * @param string $property
     * @return mixed
     */
    public function getExtraProperty(string $property):mixed
    {
        return optional($a)[$property];
    }
}
