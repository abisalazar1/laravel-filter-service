<?php

namespace Devespresso\DataFiltering\Traits;

use Devespresso\DataFiltering\Services\Filters\BaseFilterService;
use function Laravel\Prompts\search;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Foundation\Auth\User;

trait EnableDatabaseFiltering
{
    /**
     * Apply filters
     *
     * @param  Builder  $query
     * @return mixed
     */
    public function filter(
        array $data,
        ?User $user,
        $query = null,
        array $extras = []
    ) {
        $filterService = BaseFilterService::class;

        if (property_exists($this, 'defaultFilterService')) {
            $filterService = $this->defaultFilterService;
        }

        return (new $filterService())
            ->setModel($this)
            ->setQuery($query)
            ->setUser($user)
            ->setData($data)
            ->setExtras($extras)
            ->filter();
    }

    /**
     * Search
     *
     * @return Builder
     */
    public function scopeSearch(Builder $builder, ?string $search)
    {
        if (!$search) {
            return $builder;
        }
        $columns = $this->searchableColumns ?? [];
        $terms = explode(' ', $search);
        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $builder->where(function ($query) use ($column, $term) {
                    $query->orWhere($column, 'LIKE', '%' . $term . '%');
                });
            }
        }

        return $builder;
    }
}
