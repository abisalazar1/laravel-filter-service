<?php

namespace Abix\DataFiltering\Traits;

use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Abix\DataFiltering\Services\Filters\BaseFilterService;

trait DatabaseFilterable
{
    /**
     * Apply filters
     *
     * @param array $data
     * @param User|null $user
     * @param Builder $query
     * @return Builder
     */
    public function filter(
        array $data,
        ?User $user,
        $query = null,
        array $extras = []
    ): Builder {
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
     * @param Builder $builder
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $builder, string $search)
    {
        $columns = $this->searchableColumns ?? [];

        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $builder->where($column, 'LIKE', '%' . $term . '%');
            }
        }

        return $builder;
    }
}
